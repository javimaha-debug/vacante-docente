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
    |--------------------------------------------------------------------------
    | Stripe (billing)
    |--------------------------------------------------------------------------
    |
    | The webhook secret verifies the signature of incoming Stripe events. The
    | secret key is used for any server-side Stripe API calls.
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Normativa / convocatorias automated sources
    |--------------------------------------------------------------------------
    |
    | BOE search endpoint and the DOGV/union pages scraped by the sync commands.
    | Overridable via env so the exact URLs can be tuned without a code change.
    | The commands fall back to sensible defaults when these are unset.
    |
    */

    'boe' => [
        'search_url' => env('BOE_SEARCH_URL', 'https://www.boe.es/datosabiertos/api/boe/api/search'),
    ],

    'dogv' => [
        'search_urls' => array_filter(explode(',', (string) env('DOGV_SEARCH_URLS', ''))),
    ],

    'convocatorias' => [
        // Optional JSON array of {url, comunidad, estado}; defaults baked into the command.
        'sources' => json_decode((string) env('CONVOCATORIAS_SOURCES', '[]'), true) ?: [],
    ],

    'temarios' => [
        // Optional comma-separated list of union/ANPE pages for enrich-sources.
        'sources' => array_filter(explode(',', (string) env('TEMARIOS_SOURCES', ''))),
    ],

];
