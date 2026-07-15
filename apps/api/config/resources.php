<?php

declare(strict_types=1);

return [
    'disk' => env('RESOURCE_STORAGE_DISK', 's3'),
    'max_upload_bytes' => 25 * 1024 * 1024,
    'upload_ttl_seconds' => 10 * 60,
    'download_ttl_seconds' => 5 * 60,
    'cleanup_grace_seconds' => 60,
    'late_upload_reap_seconds' => 24 * 60 * 60,
    'staging_lifecycle_max_days' => 1,
    'allowed_mime_types' => [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'text/plain' => ['txt'],
        'text/markdown' => ['md', 'markdown'],
    ],
];
