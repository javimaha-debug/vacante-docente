<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VAPID keys
    |--------------------------------------------------------------------------
    |
    | Required to send Web Push notifications. Generate a key pair once with:
    |   php artisan webpush:vapid
    | and copy the values into your .env. The public key is exposed to the
    | browser; keep the private key secret.
    |
    */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL', 'mailto:admin@example.com')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],
];
