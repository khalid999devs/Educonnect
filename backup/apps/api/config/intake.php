<?php

declare(strict_types=1);

return [
    'fetch_timeout_seconds' => 10,
    'fetch_connect_timeout_seconds' => 5,
    'max_redirects' => 3,
    /*
    |--------------------------------------------------------------------------
    | Acquisition size caps
    |--------------------------------------------------------------------------
    |
    | `max_fetch_bytes` bounds LINK intakes. It stays deliberately tight: the
    | body comes from an arbitrary remote host reached over the network, so it
    | is the DoS/SSRF-amplification surface and 5 MB is generous for a page.
    |
    | `max_file_read_bytes` bounds FILE intakes and tracks `resources.max_upload_bytes`.
    | A stored file is not remote content: it was already size-declared, magic-checked,
    | hash-verified, and byte-capped by the upload path before it reached storage,
    | and it is read from our own bucket. Holding file intakes to the link cap made
    | a 6 MB PPTX pass upload and then fail intake with `content_too_large` - the
    | product accepted the file and then refused to read it. Raising this cap is the
    | correct fix; lowering the upload limit or advertising 5 MB in the UI would
    | throw away capability the storage path already provides.
    |
    */
    'max_fetch_bytes' => 5 * 1024 * 1024,
    'max_file_read_bytes' => 25 * 1024 * 1024,
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
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Office package extraction (DOCX, PPTX)
    |--------------------------------------------------------------------------
    |
    | OOXML files are ZIP archives, so extraction inherits decompression-bomb
    | exposure. Both caps are enforced from the central directory BEFORE any
    | entry is inflated. A real lecture deck sits far under both.
    |
    */
    'office' => [
        'max_entries' => max(1, (int) env('INTAKE_OFFICE_MAX_ENTRIES', 512)),
        'max_uncompressed_bytes' => max(1, (int) env('INTAKE_OFFICE_MAX_UNCOMPRESSED_BYTES', 64 * 1024 * 1024)),
    ],

    'classification' => [
        'max_output_retries' => 2,
        'max_suggestions' => 10,
        'max_courses' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Optical character recognition
    |--------------------------------------------------------------------------
    | Image resources are read with the Tesseract engine (invoked out of
    | process). `binary` is resolved from PATH by default; set an absolute path
    | if the queue worker runs without the interactive shell's PATH.
    */
    'ocr' => [
        'binary' => env('INTAKE_OCR_BINARY', 'tesseract'),
        'languages' => env('INTAKE_OCR_LANGUAGES', 'eng'),
        'timeout_seconds' => (int) env('INTAKE_OCR_TIMEOUT_SECONDS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reliability (Phase 28)
    |--------------------------------------------------------------------------
    |
    | An item that sits in a transient state (queued/extracting/organizing)
    | longer than `stranded_after_seconds` is treated as stranded - its worker
    | was lost or hard-killed without a graceful `failed()` - and recovered by
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
