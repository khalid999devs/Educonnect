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
];
