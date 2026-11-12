<?php

namespace App\Control;

use App\Support\SchoolConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * LEF's Paystack account, used by the Control Centre: every school is a subaccount of it, paid its own share
 * directly (LEF takes 0%; the school bears Paystack's fee). Keys are stored encrypted in the Control Centre.
 */
class PaystackLef
{
    public static function keys(): array
    {
        $saved = SchoolConfig::get('paystack_lef');
        try {
            return $saved ? (array) json_decode(Crypt::decryptString($saved), true) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function saveKeys(string $secret, string $public): void
    {
        SchoolConfig::set('paystack_lef', Crypt::encryptString(json_encode(['secret' => $secret, 'public' => $public])));
        Cache::forget('paystack_banks_gh');
    }

    public static function ready(): bool
    {
        return !empty(self::keys()['secret']);
    }

    /** What the Control Centre shows: never the keys themselves. */
    public static function summary(): array
    {
        $k = self::keys();

        return ['ready' => self::ready(), 'test' => Str::startsWith($k['secret'] ?? '', 'sk_test'),
            'secret_hint' => isset($k['secret']) ? substr($k['secret'], -4) : null, 'public_hint' => isset($k['public']) ? substr($k['public'], -4) : null];
    }

    protected static function http()
    {
        return Http::withToken(self::keys()['secret'] ?? '')->acceptJson()->timeout(30)->baseUrl(rtrim(config('paystack.base_url'), '/'));
    }

    /** Ghana banks and mobile money networks a school can be paid into. */
    public static function banks(): array
    {
        if (!self::ready()) return [];

        return Cache::remember('paystack_banks_gh', 86400, function () {
            $res = self::http()->get('/bank', ['country' => 'ghana', 'perPage' => 200]);

            return collect((array) $res->json('data'))->filter(function ($b) { return !empty($b['active']); })
                ->map(function ($b) { return ['code' => $b['code'], 'name' => $b['name'], 'type' => $b['type'] ?? '']; })
                ->sortBy(function ($b) { return ($b['type'] === 'mobile_money' ? '0' : '1').$b['name']; })->values()->all();
        });
    }

    public static function subaccount(?string $code): ?array
    {
        if (!$code || !self::ready()) return null;
        $res = self::http()->get('/subaccount/'.rawurlencode($code));
        $d = (array) $res->json('data');
        if (!$res->ok() || !$d) return null;

        return ['code' => $d['subaccount_code'] ?? $code, 'business_name' => $d['business_name'] ?? null, 'bank' => $d['settlement_bank'] ?? null,
            'account_number' => $d['account_number'] ?? null, 'percentage_charge' => $d['percentage_charge'] ?? null, 'active' => (bool) ($d['active'] ?? false)];
    }

    /** Create the school's subaccount, or change where it is paid. Returns the subaccount code or throws. */
    public static function saveSubaccount(?string $code, string $business, string $bank, string $account, string $school): string
    {
        $body = ['business_name' => $business, 'settlement_bank' => $bank, 'account_number' => $account, 'percentage_charge' => 0,
            'description' => 'EduNetwork: '.$school];
        $res = $code ? self::http()->put('/subaccount/'.rawurlencode($code), $body) : self::http()->post('/subaccount', $body);
        if (!$res->successful() || !$res->json('status')) {
            throw new \RuntimeException('Paystack did not accept it: '.($res->json('message') ?: 'HTTP '.$res->status()));
        }

        return (string) ($res->json('data.subaccount_code') ?: $code);
    }
}
