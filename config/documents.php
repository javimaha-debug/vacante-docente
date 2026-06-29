<?php

return [
    /*
    | Disk where user-uploaded documents live. Production uses DigitalOcean
    | Spaces ('spaces'); local dev can fall back to 'local' via DOCUMENTS_DISK.
    | Files are never exposed by path — access is via short-lived signed routes.
    */
    'disk' => env('DOCUMENTS_DISK', env('FILESYSTEM_DISK') === 'spaces' ? 'spaces' : 'spaces'),

    // Max upload size per file (KB) and allowed extensions.
    'max_kb' => (int) env('DOCUMENTS_MAX_KB', 51200), // 50 MB
    'allowed_ext' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'],

    // Minutes a generated view link stays valid.
    'view_ttl_minutes' => 15,
];
