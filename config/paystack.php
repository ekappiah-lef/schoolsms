<?php

/*
 * Paystack: parents pay school fees online by mobile money (MTN, Telecel, AirtelTigo) or card.
 * Each school uses its own Paystack account (its keys), or LEF's account with the school's subaccount
 * code, so the money is settled to the school. Keys starting sk_test / pk_test are Paystack's test mode.
 *
 *   PAYSTACK_SECRET_KEY, PAYSTACK_PUBLIC_KEY, PAYSTACK_SUBACCOUNT (optional)
 *   Webhook (Paystack dashboard → Settings → API keys & webhooks): https://<school>/paystack/webhook
 */
return [
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'subaccount' => env('PAYSTACK_SUBACCOUNT'),
    'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
];
