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
        $p = OnlinePayment::create(['reference' => 'EDU-'.Str::upper(Str::random(16)), 'status' => 'pending', 'student_id' => $sr->user_id, 'amount' => Money::r($amount),
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
        $body += self::split();

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

    /** The school's subaccount gets the whole amount (LEF 0%) and bears Paystack's fee. */
    protected static function split(): array
    {
        return config('paystack.subaccount') ? ['subaccount' => config('paystack.subaccount'), 'bearer' => 'subaccount'] : [];
    }

    /** Paystack's code for the network of a Ghana number (MTN, Telecel, AirtelTigo), or null. */
    public static function network(string $phone): ?string
    {
        $n = preg_replace('/\D/', '', $phone);
        $local = strpos($n, '233') === 0 ? '0'.substr($n, 3) : $n;
        $p = substr($local, 0, 3);
        if (in_array($p, ['024', '025', '053', '054', '055', '059'], true)) return 'mtn';
        if (in_array($p, ['020', '050'], true)) return 'vod';
        if (in_array($p, ['026', '027', '056', '057'], true)) return 'atl';

        return null;
    }

    /**
     * Send a payment prompt to the parent's phone (Student payments). Returns [payment, what happens next]:
     * "prompt" (approve on the phone), "otp" (type the code the parent received), or "done".
     */
    public static function charge(StudentRecord $sr, float $amount, string $phone, string $network, ?int $by): array
    {
        if (!self::configured()) throw new \RuntimeException('Online payment is not set up for this school.');
        $local = preg_replace('/\D/', '', $phone);
        if (strpos($local, '233') === 0) $local = '0'.substr($local, 3);
        if (strlen($local) !== 10) throw new \RuntimeException('Enter a valid Ghana mobile money number, e.g. 024 123 4567.');
        $email = collect(Notices::recipients($sr)['emails'])->first()
            ?: 'fees@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'school.example');
        $p = OnlinePayment::create(['reference' => 'EDU-'.Str::upper(Str::random(16)), 'status' => 'pending', 'student_id' => $sr->user_id, 'amount' => Money::r($amount),
            'email' => $email, 'phone' => $local, 'network' => $network, 'initiated_by' => $by]);
        try {
            $res = self::http()->post('/charge', [
                'email' => $email, 'amount' => (int) round($amount * 100), 'currency' => 'GHS', 'reference' => $p->reference,
                'mobile_money' => ['phone' => $local, 'provider' => $network],
                'metadata' => ['student' => $sr->user->name, 'adm_no' => $sr->adm_no, 'school' => Qs::getSystemName()],
            ] + self::split());
        } catch (Throwable $e) {
            $p->update(['status' => 'failed', 'reason' => mb_substr($e->getMessage(), 0, 250)]);
            throw new \RuntimeException('Could not reach Paystack. Try again shortly.');
        }

        return [$p, self::next($p, $res)];
    }

    /** The code the parent received (some networks ask for one before the prompt). */
    public static function submitOtp(OnlinePayment $p, string $otp): array
    {
        $res = self::http()->post('/charge/submit_otp', ['otp' => $otp, 'reference' => $p->reference]);

        return self::next($p, $res);
    }

    protected static function next(OnlinePayment $p, $res): array
    {
        $status = $res->json('data.status');
        $text = $res->json('data.display_text') ?: $res->json('data.message');
        if (!$res->successful() && !$status) {
            $p->update(['status' => 'failed', 'reason' => mb_substr('HTTP '.$res->status().' '.$res->json('message'), 0, 250)]);
            throw new \RuntimeException('Paystack did not accept the payment: '.($res->json('message') ?: 'HTTP '.$res->status()).'.');
        }
        if ($status === 'send_otp') return ['step' => 'otp', 'text' => $text];
        if ($status === 'success') {
            self::verify($p);
            return ['step' => 'done', 'text' => $text];
        }
        if ($status === 'failed') {
            $p->update(['status' => 'failed', 'reason' => mb_substr((string) ($res->json('data.gateway_response') ?: $text ?: 'Declined'), 0, 250)]);
            throw new \RuntimeException('The payment was declined'.($text ? ': '.$text : '.'));
        }

        return ['step' => 'prompt', 'text' => $text];
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
            \App\Http\Controllers\SupportTeam\ReceiptController::autoSend($a['kind'] === 'school' ? 'school' : 'optional', (int) $a['id'], true);
        }
    }

    /** Paystack signs each webhook with the secret key. */
    public static function validSignature(string $body, ?string $signature): bool
    {
        return self::configured() && $signature && hash_equals(hash_hmac('sha512', $body, (string) config('paystack.secret_key')), $signature);
    }
}
