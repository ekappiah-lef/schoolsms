<?php

namespace App\Support;

use App\Helpers\Qs;
use App\Models\OnlinePayment;
use App\Models\StudentRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Paystack checkout: the parent is sent to Paystack's page, pays by mobile money (any network) or card,
 * and comes back. A payment is applied to the fees only after Paystack confirms it (verify), exactly once.
 */
class Paystack
{
    public static function configured(): bool
    {
        return (bool) config('paystack.secret_key');
    }

    public static function testMode(): bool
    {
        return Str::startsWith((string) config('paystack.secret_key'), 'sk_test');
    }

    protected static function http()
    {
        return Http::withToken(config('paystack.secret_key'))->acceptJson()->timeout(30)->baseUrl(rtrim(config('paystack.base_url'), '/'));
    }

    /** Start a checkout; returns [payment, Paystack page URL] or throws with a readable message. */
    public static function start(StudentRecord $sr, float $amount, ?int $by): array
    {
        if (!self::configured()) throw new \RuntimeException('Online payment is not set up for this school.');
        $email = collect(Notices::recipients($sr)['emails'])->first()
            ?: 'fees@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'school.example');
        $p = OnlinePayment::create(['reference' => 'EDU-'.Str::upper(Str::random(16)), 'student_id' => $sr->user_id, 'amount' => Money::r($amount),
            'email' => $email, 'initiated_by' => $by]);

        $body = [
            'email' => $email,
            'amount' => (int) round($amount * 100),   // pesewas
            'currency' => 'GHS',
            'reference' => $p->reference,
            'callback_url' => route('paystack.callback'),
            'channels' => ['mobile_money', 'card'],
            'metadata' => ['student' => $sr->user->name, 'adm_no' => $sr->adm_no, 'school' => Qs::getSystemName()],
        ];
        if (config('paystack.subaccount')) $body['subaccount'] = config('paystack.subaccount');

        try {
            $res = self::http()->post('/transaction/initialize', $body);
        } catch (Throwable $e) {
            $p->update(['status' => 'failed', 'reason' => mb_substr($e->getMessage(), 0, 250)]);
            throw new \RuntimeException('Could not reach Paystack. Try again shortly.');
        }
        if (!$res->ok() || !$res->json('status') || !$res->json('data.authorization_url')) {
            $p->update(['status' => 'failed', 'reason' => mb_substr('HTTP '.$res->status().' '.$res->json('message'), 0, 250)]);
            throw new \RuntimeException('Paystack did not accept the payment: '.($res->json('message') ?: 'HTTP '.$res->status()).'.');
        }

        return [$p, $res->json('data.authorization_url')];
    }

    /** Ask Paystack how the payment went; a confirmed payment is applied to the fees once. */
    public static function verify(OnlinePayment $p): OnlinePayment
    {
        if ($p->status !== 'pending') return $p;
        try {
            $res = self::http()->get('/transaction/verify/'.rawurlencode($p->reference));
        } catch (Throwable $e) {
            return $p;
        }
        $d = (array) $res->json('data');
        $status = $d['status'] ?? null;
        if ($status === 'success') {
            // The amount and currency must be what was asked for.
            if ((int) ($d['amount'] ?? 0) !== (int) round($p->amount * 100) || ($d['currency'] ?? '') !== $p->currency) {
                $p->update(['status' => 'failed', 'reason' => 'Amount or currency did not match']);
                return $p->fresh();
            }
            self::settle($p, true, $d);
        } elseif (in_array($status, ['failed', 'reversed'], true)) {   // "abandoned": not paid yet, the parent may still finish
            self::settle($p, false, $d);
        }

        return $p->fresh();
    }

    protected static function settle(OnlinePayment $p, bool $ok, array $d): void
    {
        $applied = [];
        DB::transaction(function () use ($p, $ok, $d, &$applied) {
            $p = OnlinePayment::where('id', $p->id)->lockForUpdate()->first();
            if ($p->status !== 'pending') return;
            $channel = $d['channel'] ?? null;
            if ($ok) {
                $applied = Fees::applyPayment($p->student_id, $p->amount, $channel === 'card' ? 'Bank transfer' : 'Mobile payment', 'Paystack '.($channel === 'card' ? 'card ' : '').$p->reference);
                $p->update(['status' => 'successful', 'channel' => $channel, 'provider_id' => (string) ($d['id'] ?? ''), 'applied_at' => now()]);
            } else {
                $p->update(['status' => 'failed', 'channel' => $channel, 'reason' => mb_substr((string) ($d['gateway_response'] ?? 'Payment was not completed'), 0, 250)]);
            }
        });
        foreach ($applied as $a) {
            \App\Http\Controllers\SupportTeam\ReceiptController::autoSend($a['kind'] === 'school' ? 'school' : 'optional', (int) $a['id']);
        }
    }

    /** Paystack signs each webhook with the secret key. */
    public static function validSignature(string $body, ?string $signature): bool
    {
        return self::configured() && $signature && hash_equals(hash_hmac('sha512', $body, (string) config('paystack.secret_key')), $signature);
    }
}
