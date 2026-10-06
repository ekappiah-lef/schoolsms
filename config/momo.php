<?php

/*
 * MTN Mobile Money (MoMo) Collection API — "Request to Pay".
 *
 *   MOMO_DRIVER=fake        simulate payments locally (no MTN account needed)
 *   MOMO_DRIVER=sandbox     https://sandbox.momodeveloper.mtn.com, environment "sandbox", currency EUR
 *   MOMO_DRIVER=production  https://proxy.momoapi.mtn.com, environment "mtnghana", currency GHS
 *
 * Credentials come from https://momodeveloper.mtn.com (sandbox) or from MTN Ghana after go-live:
 * the Collections subscription key, an API user (UUID) and its API key.
 */
$driver = env('MOMO_DRIVER', 'fake');
$live = $driver === 'production';

return [
    'driver' => $driver,
    'base_url' => env('MOMO_BASE_URL', $live ? 'https://proxy.momoapi.mtn.com' : 'https://sandbox.momodeveloper.mtn.com'),
    'environment' => env('MOMO_TARGET_ENVIRONMENT', $live ? 'mtnghana' : 'sandbox'),
    'currency' => env('MOMO_CURRENCY', $live ? 'GHS' : 'EUR'),
    'subscription_key' => env('MOMO_COLLECTION_SUBSCRIPTION_KEY'),
    'api_user' => env('MOMO_API_USER'),
    'api_key' => env('MOMO_API_KEY'),
    // Public https URL MTN calls when a payment finishes (optional; the app also checks the status itself).
    'callback_url' => env('MOMO_CALLBACK_URL'),
];
