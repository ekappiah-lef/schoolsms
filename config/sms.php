<?php

return [
    /*
     | Driver used for admission / fee SMS.
     | "log" writes messages to storage/logs/laravel.log and sends nothing.
     | "smsonlinegh", "arkesel" and "hubtel" need the matching credentials below.
     */
    'driver' => env('SMS_DRIVER', 'log'),

    // Sender name shown on the phone (max 11 characters, registered with the provider).
    'sender' => env('SMS_SENDER_ID'),

    'smsonlinegh' => [
        'api_key' => env('SMSONLINEGH_API_KEY'),
    ],

    /*
     | Comma-separated emails / phone numbers. When set, admission notices and
     | receipts are only sent to these contacts (use while the app holds demo data).
     */
    'allowlist' => env('NOTICE_ALLOWLIST'),

    'arkesel' => [
        'api_key' => env('ARKESEL_API_KEY'),
    ],

    'hubtel' => [
        'client_id' => env('HUBTEL_CLIENT_ID'),
        'client_secret' => env('HUBTEL_CLIENT_SECRET'),
    ],
];
