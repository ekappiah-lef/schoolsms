<?php

namespace App\Support;

use App\Models\MomoPayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * MTN MoMo Collection API (Request to Pay). The parent gets a prompt on their phone, approves it
 * with their MoMo PIN, and the payment is then applied to the student's fees (oldest debts first).
 *
 *   1. POST /collection/token/                      Basic api_user:api_key  -> access_token (1 hour)
 *   2. POST /collection/v1_0/requesttopay           X-Reference-Id: uuid    -> 202 Accepted
 *   3. GET  /collection/v1_0/requesttopay/{uuid}                             -> status PENDING | SUCCESSFUL | FAILED
 *
 * The "fake" driver simulates step 2–3 (successful after a few seconds) for testing without MTN keys.
 */
class MoMo
{
    public static function configured(): bool
    {
        if (config('momo.driver') === 'fake') {
            return true;
        }

        return config('momo.subscription_key') && config('momo.api_user') && config('momo.api_key');
    }

    /** Start a payment; returns the MomoPayment (status pending) or throws with a readable message. */
    public static function requestToPay(int $studentId, string $phone, int $amount, ?int $initiatedBy, string $note): MomoPayment
    {
        $msisdn = Notices::normalisePhone($phone);
        if (!$msisdn || strlen($msisdn) < 12 || strpos($msisdn, '233') !== 0) {
            throw new \RuntimeException('Enter a valid Ghana MTN number, e.g. 024 123 4567.');
        }
        if (!self::configured()) {
            throw new \RuntimeException('MTN MoMo is not set up yet (MOMO_* settings).');
        }

        $p = MomoPayment::create([
            'reference' => (string) Str::uuid(), 'student_id' => $studentId, 'phone' => $msisdn, 'amount' => $amount,
            'currency' => config('momo.currency'), 'status' => 'pending', 'initiated_by' => $initiatedBy,
        ]);

        if (config('momo.driver') === 'fake') {
            return $p;
        }

        $headers = [
            'X-Reference-Id' => $p->reference,
            'X-Target-Environment' => config('momo.environment'),
            'Ocp-Apim-Subscription-Key' => config('momo.subscription_key'),
        ];
        if (config('momo.callback_url')) {
            $headers['X-Callback-Url'] = config('momo.callback_url');
        }

        try {
            $res = Http::withToken(self::token())->withHeaders($headers)->timeout(30)
                ->post(rtrim(config('momo.base_url'), '/').'/collection/v1_0/requesttopay', [
                    'amount' => (string) $amount,
                    'currency' => config('momo.currency'),
                    'externalId' => (string) $p->id,
                    'payer' => ['partyIdType' => 'MSISDN', 'partyId' => $msisdn],
                    'payerMessage' => mb_substr($note, 0, 160),
                    'payeeNote' => mb_substr($note, 0, 160),
                ]);
        } catch (Throwable $e) {
            $p->update(['status' => 'failed', 'reason' => mb_substr($e->getMessage(), 0, 250)]);
            throw new \RuntimeException('Could not reach MTN MoMo. Try again shortly.');
        }

        if ($res->status() !== 202) {
            $p->update(['status' => 'failed', 'reason' => 'HTTP '.$res->status().' '.mb_substr($res->body(), 0, 200)]);
            throw new \RuntimeException('MTN MoMo refused the request ('.$res->status().').');
        }

        return $p;
    }

    /**
     * Ask MTN for the latest status. When it has succeeded, apply the money to the student's fees
     * exactly once. Returns the refreshed payment.
     */
    public static function refresh(MomoPayment $p): MomoPayment
    {
        if ($p->status !== 'pending') {
            return $p;
        }

        if (config('momo.driver') === 'fake') {
            // Simulation: approved 8 seconds after the request; numbers ending in 0 are declined.
            if ($p->created_at->diffInSeconds(now()) >= 8) {
                $ok = substr($p->phone, -1) !== '0';
                self::settle($p, $ok ? 'SUCCESSFUL' : 'FAILED', $ok ? 'FAKE'.random_int(100000, 999999) : null, $ok ? null : 'Declined (test number)');
            }
            return $p->fresh();
        }

        try {
            $res = Http::withToken(self::token())->withHeaders([
                'X-Target-Environment' => config('momo.environment'),
                'Ocp-Apim-Subscription-Key' => config('momo.subscription_key'),
            ])->timeout(20)->get(rtrim(config('momo.base_url'), '/').'/collection/v1_0/requesttopay/'.$p->reference);
        } catch (Throwable $e) {
            return $p;
        }
        if ($res->ok()) {
            $j = $res->json();
            if (in_array($j['status'] ?? '', ['SUCCESSFUL', 'FAILED'], true)) {
                self::settle($p, $j['status'], $j['financialTransactionId'] ?? null, $j['reason']['message'] ?? $j['reason'] ?? null);
            }
        }

        return $p->fresh();
    }

    /** Record the outcome; a successful payment is applied to the fees (once, inside a lock). */
    protected static function settle(MomoPayment $p, string $status, ?string $txn, $reason): void
    {
        DB::transaction(function () use ($p, $status, $txn, $reason) {
            $p = MomoPayment::where('id', $p->id)->lockForUpdate()->first();
            if ($p->status !== 'pending') {
                return;
            }
            if ($status === 'SUCCESSFUL') {
                Fees::applyPayment($p->student_id, (int) $p->amount, 'Mobile payment', 'MTN MoMo '.($txn ?: $p->reference));
                $p->update(['status' => 'successful', 'financial_transaction_id' => $txn, 'applied_at' => now()]);
            } else {
                $p->update(['status' => 'failed', 'reason' => is_string($reason) ? mb_substr($reason, 0, 250) : 'Payment was not completed']);
            }
        });
    }

    protected static function token(): string
    {
        return Cache::remember('momo.collection.token', 50 * 60, function () {
            $res = Http::withBasicAuth(config('momo.api_user'), config('momo.api_key'))
                ->withHeaders(['Ocp-Apim-Subscription-Key' => config('momo.subscription_key')])
                ->timeout(20)->post(rtrim(config('momo.base_url'), '/').'/collection/token/');
            if (!$res->ok() || !$res->json('access_token')) {
                throw new \RuntimeException('MTN MoMo login failed ('.$res->status().'). Check the MOMO_* settings.');
            }
            return $res->json('access_token');
        });
    }
}
