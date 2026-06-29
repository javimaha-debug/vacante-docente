<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The API is stateless and bearer-token based (no session cookies), so
    | credentials are not shared cross-origin. Origins are restricted to the
    | app's own domains rather than the framework default wildcard. Extra
    | origins can be added via the CORS_ALLOWED_ORIGINS env (comma-separated).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_unique(array_merge(
        [rtrim((string) env('APP_URL', 'https://doccentia.es'), '/')],
        ['https://doccentia.es', 'https://vacantes.movvos.com'],
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
