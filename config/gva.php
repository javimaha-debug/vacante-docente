<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-import of detected GVA listings
    |--------------------------------------------------------------------------
    |
    | When enabled, the daily GVA monitor downloads newly detected listing PDFs
    | (participants / vacancies) and imports them automatically, then notifies
    | the admins so they can review. Set GVA_AUTO_IMPORT=false to only detect
    | and notify (manual import).
    |
    */
    'auto_import' => env('GVA_AUTO_IMPORT', true),

    // Where downloaded listing PDFs are stored (relative to the 'local' disk).
    'download_dir' => 'pdfs/gva/auto',

    /*
    |--------------------------------------------------------------------------
    | Headless renderer (for the GVA's JavaScript pages)
    |--------------------------------------------------------------------------
    |
    | The inicio / adjudicaciones-continuas pages load their PDF links via JS,
    | so we render them with a headless Chromium (scripts/gva-render.mjs). When
    | disabled or unavailable the monitor falls back to static scraping.
    |
    */
    'render' => [
        'enabled' => env('GVA_RENDER_ENABLED', true),
        'node' => env('GVA_RENDER_NODE', 'node'),
        'script' => base_path('scripts/gva-render.mjs'),
        'chromium' => env('GVA_CHROMIUM_PATH'), // null → playwright-managed browser
        'timeout' => (int) env('GVA_RENDER_TIMEOUT', 90),
    ],

    // GVA source pages, by category.
    'pages' => [
        'inicio' => 'https://ceice.gva.es/va/web/rrhh-educacion/inicio',
        'continua' => 'https://ceice.gva.es/va/web/rrhh-educacion/adjudicaciones-continuas',
    ],
];
