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
];
