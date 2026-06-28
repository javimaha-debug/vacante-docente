<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Maps (server-side only)
    |--------------------------------------------------------------------------
    |
    | Used by the Geocoding API and the Distance Matrix API. This key must
    | NEVER be exposed to the frontend; all Google Maps calls are proxied
    | through the Laravel backend.
    |
    */

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth (phase 2 — Laravel Socialite)
    |--------------------------------------------------------------------------
    |
    | Auth is disabled in phase 1. These values are scaffolded so that
    | Socialite can be enabled in phase 2 without further config changes.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    /*
    | Microsoft OAuth (Socialite). Requires the socialite-providers/microsoft
    | package installed and registered. Enabled when MICROSOFT_CLIENT_ID is set.
    */
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
        'tenant' => env('MICROSOFT_TENANT', 'common'),
    ],

    /*
    | Apple OAuth (Socialite). Requires the socialite-providers/apple package.
    | Enabled when APPLE_CLIENT_ID is set (needs a private key, team & key id).
    */
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI'),
    ],

];
