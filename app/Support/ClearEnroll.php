<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to ClearEnroll's school-system API (config/clearenroll.php).
 *
 *  - Verify a student or teacher (the same lookup as the ClearEnroll portal).
 *  - Send every student who owes anything, with the amount (full sync: every day at 23:00, or "Sync now").
 *  - Send one student's new balance straight after a payment (or a reversal) is recorded here.
 *
 * Only students whose parent accepted the admission agreement (which covers ClearEnroll) are sent.
 * The school system's own fees, invoices and reports are unaffected: this only sends copies of balances.
 */
class ClearEnroll
{
    public static function enabled(): bool
    {
        return config('clearenroll.url') && config('clearenroll.key');
    }

    protected static function call(string $method, string $path, array $body = [])
    {
        $req = Http::withToken((string) config('clearenroll.key'))->acceptJson()->timeout(config('clearenroll.timeout', 15));
        $url = config('clearenroll.url').$path;

        return $method === 'GET' ? $req->get($url) : $req->post($url, $body);
    }

    /** Web address of a photo stored on ClearEnroll (students / teachers), as the portal builds it. */
    public static function photoUrl(?string $photo, string $folder): ?string
    {
        if (!$photo || !self::enabled()) return null;
        $base = preg_replace('#/integration/v1$#', '', config('clearenroll.url'));

        return strpos($photo, 'uploads/') === 0 ? $base.'/'.$photo : $base.'/uploads/'.$folder.'/'.rawurlencode($photo);
    }

    /** Which ClearEnroll school the key belongs to, or why it cannot connect. */
    public static function ping(): array
    {
        if (!self::enabled()) return ['ok' => false, 'message' => 'ClearEnroll is not connected yet (CLEARENROLL_API_URL / CLEARENROLL_API_KEY).'];
        try {
            $r = self::call('GET', '/ping');
            return $r->ok() ? ['ok' => true, 'school' => $r->json('school')] : ['ok' => false, 'message' => $r->json('message') ?: 'ClearEnroll refused the key ('.$r->status().').'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach ClearEnroll.'];
        }
    }

    /** Search ClearEnroll for a student (kind = student) or teacher (kind = teacher). */
    public static function verify(string $kind, string $query): array
    {
        if (!self::enabled()) return ['error' => 'ClearEnroll is not connected yet.'];
        try {
            $r = self::call('POST', $kind === 'teacher' ? '/verify/teacher' : '/verify/student', ['query' => $query]);
            if ($r->status() === 429) return ['error' => $r->json('message') ?: 'Too many searches today.'];
            if (!$r->ok()) return ['error' => $r->json('message') ?: 'ClearEnroll could not search right now.'];

            return $r->json();
        } catch (\Throwable $e) {
            return ['error' => 'Could not reach ClearEnroll.'];
        }
    }

    /** What ClearEnroll needs about a student, with their balance now (what the parent's invoice says is due). */
    public static function payload(int $studentId): ?array
    {
        $s = DB::table('student_records as sr')->join('users as u', 'u.id', '=', 'sr.user_id')
            ->leftJoin('student_details as sd', 'sd.user_id', '=', 'u.id')
            ->leftJoin('users as p', 'p.id', '=', 'sr.my_parent_id')
            ->leftJoin('parent_details as pd', 'pd.user_id', '=', 'p.id')
            ->leftJoin('my_classes as c', 'c.id', '=', 'sr.my_class_id')
            ->where('sr.user_id', $studentId)
            ->first(['u.id', 'u.name', 'u.dob', 'u.gender', 'sr.adm_no', 'sr.session', 'sd.first_name', 'sd.middle_name', 'sd.last_name', 'sd.terms_accepted_at',
                'p.name as parent', 'p.phone as parent_phone', 'pd.father_name', 'pd.mother_name', 'pd.guardian_name', 'c.name as class']);
        if (!$s) return null;

        $parts = preg_split('/\s+/', trim((string) $s->name));
        $first = $s->first_name ?: ($parts[0] ?? '');
        $last = $s->last_name ?: (count($parts) > 1 ? end($parts) : '');
        $rel = $s->parent && $s->parent === $s->father_name ? 'Father' : ($s->parent && $s->parent === $s->mother_name ? 'Mother' : 'Guardian');
        $gender = in_array(ucfirst(strtolower((string) $s->gender)), ['Male', 'Female'], true) ? ucfirst(strtolower($s->gender)) : null;

        return [
            'external_id' => (string) $s->id,
            'first_name' => $first, 'last_name' => $last, 'other_names' => $s->middle_name ?: null,
            'date_of_birth' => $s->dob ? date('Y-m-d', strtotime($s->dob)) : null,
            'gender' => $gender,
            'admission_no' => $s->adm_no, 'class_name' => $s->class, 'year' => $s->session,
            'parent' => $s->parent && $s->parent_phone ? ['full_name' => $s->parent, 'phone' => $s->parent_phone, 'relationship' => $rel] : null,
            'balance' => (int) Fees::termInvoice((int) $s->id)['total'],
            'currency' => 'GHS',
            '_consented' => (bool) $s->terms_accepted_at,
            '_name' => $s->name,
        ];
    }

    /** Why ClearEnroll would turn a student away (missing details), or null. */
    public static function problem(array $p): ?string
    {
        $missing = array_keys(array_filter(['date of birth' => !$p['date_of_birth'], 'gender' => !$p['gender'], 'parent name and phone' => !$p['parent'], 'last name' => !$p['last_name']]));

        return $missing ? 'Missing '.implode(', ', $missing) : null;
    }

    /** Every current student who owes anything and whose parent consented, with problems found. */
    public static function debtors(): array
    {
        $ids = DB::table('student_records')->where('grad', 0)->pluck('user_id');
        $out = [];
        foreach ($ids as $id) {
            $p = self::payload((int) $id);
            if ($p && $p['balance'] > 0) $out[] = $p;
        }

        return $out;
    }

    protected static function strip(array $p): array
    {
        return array_diff_key($p, ['_consented' => 1, '_name' => 1]);
    }

    protected static function termKey(): string
    {
        [$y, $t] = FinancePeriod::termOf(now());

        return $y.'-'.($y + 1).':'.$t;
    }

    protected static function record(string $kind, ?int $studentId, int $sent, array $r = [], ?string $error = null): array
    {
        $row = [
            'kind' => $kind, 'term' => self::termKey(), 'student_id' => $studentId, 'sent' => $sent,
            'flagged' => (int) ($r['flagged'] ?? 0), 'updated' => (int) ($r['updated'] ?? 0), 'cleared' => (int) ($r['cleared'] ?? 0),
            'rejected' => !empty($r['rejected']) ? json_encode($r['rejected']) : null, 'error' => $error ? mb_substr($error, 0, 255) : null,
            'user_id' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('clearenroll_syncs')->insert($row);

        return $row;
    }

    /**
     * Full sync: every consented student who owes anything, with the amount. Students who were flagged
     * before and owe nothing now are cleared by ClearEnroll.
     */
    public static function syncAll(): array
    {
        if (!self::enabled()) return ['error' => 'ClearEnroll is not connected yet.'];
        $list = array_values(array_filter(self::debtors(), function ($p) { return $p['_consented']; }));
        try {
            $r = self::call('POST', '/balances', ['full' => true, 'students' => array_map([self::class, 'strip'], $list)]);
            if (!$r->ok()) return self::record('full', null, count($list), [], $r->json('message') ?: 'ClearEnroll answered '.$r->status());

            return self::record('full', null, count($list), $r->json());
        } catch (\Throwable $e) {
            Log::warning('ClearEnroll full sync failed: '.$e->getMessage());
            return self::record('full', null, count($list), [], 'Could not reach ClearEnroll');
        }
    }

    /**
     * One student's balance changed here (a payment, a sale paid, a reversal): send the new balance now.
     * Runs after the page has answered, so a slow connection never holds up the cashier. If it fails,
     * the next full sync puts it right.
     */
    public static function studentChanged(int $studentId, ?int $amount = null, ?string $reference = null): void
    {
        if (!self::enabled()) return;
        dispatch(function () use ($studentId, $amount, $reference) {
            $p = self::payload($studentId);
            if (!$p || !$p['_consented']) return;
            if ($amount) $p['payment'] = ['amount' => $amount, 'reference' => $reference];
            try {
                $r = self::call('POST', '/balances', ['students' => [self::strip($p)]]);
                self::record('student', $studentId, 1, $r->ok() ? $r->json() : [], $r->ok() ? null : ($r->json('message') ?: 'ClearEnroll answered '.$r->status()));
            } catch (\Throwable $e) {
                self::record('student', $studentId, 1, [], 'Could not reach ClearEnroll');
            }
        })->afterResponse();
    }

    /** Full sync once a term (called daily by the scheduler): only if this term has not been synced yet. */
    public static function syncIfNewTerm(): ?array
    {
        $done = DB::table('clearenroll_syncs')->where('kind', 'full')->where('term', self::termKey())->whereNull('error')->exists();

        return $done ? null : self::syncAll();
    }
}
