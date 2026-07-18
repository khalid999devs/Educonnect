<?php

declare(strict_types=1);

return [
    'fetch_timeout_seconds' => 10,
    'fetch_connect_timeout_seconds' => 5,
    'max_redirects' => 3,
    'max_fetch_bytes' => 5 * 1024 * 1024,
    'max_extracted_characters' => 200000,
    'max_attempts' => 3,
    'allowed_link_content_types' => [
        'text/html',
        'text/plain',
        'text/markdown',
        'application/xhtml+xml',
    ],
    'extractable_file_mime_types' => [
        'text/plain',
        'text/markdown',
    ],
    'classification' => [
        'max_output_retries' => 2,
        'max_suggestions' => 10,
        'max_courses' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reliability (Phase 28)
    |--------------------------------------------------------------------------
    |
    | An item that sits in a transient state (queued/extracting/organizing)
    | longer than `stranded_after_seconds` is treated as stranded — its worker
    | was lost or hard-killed without a graceful `failed()` — and recovered by
    | the `intake:recover-stranded` scheduler. Retryable failures are then
    | auto-requeued with exponential backoff, bounded by the attempt budget.
    |
    */

    'stranded_after_seconds' => max(60, (int) env('INTAKE_STRANDED_AFTER_SECONDS', 300)),

    'auto_retry' => [
        'enabled' => (bool) env('INTAKE_AUTO_RETRY_ENABLED', true),
        'base_delay_seconds' => max(1, (int) env('INTAKE_AUTO_RETRY_BASE_DELAY_SECONDS', 60)),
        'max_delay_seconds' => max(1, (int) env('INTAKE_AUTO_RETRY_MAX_DELAY_SECONDS', 900)),
    ],
];
