<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Telemetry substrate
    |--------------------------------------------------------------------------
    |
    | The operational metrics substrate (Phase 28). Recording is best-effort and
    | must never break the request or job that emits it; when disabled, every
    | recorder call is a no-op. Durable rows land in `telemetry_events`
    | (AI-provider calls, background-job health, captured server errors); HTTP
    | request latency is aggregated in the cache store (see `http`).
    |
    */

    'enabled' => (bool) env('TELEMETRY_ENABLED', true),

    // Durable events older than this are prunable by `telemetry:prune`.
    'retention_days' => max(1, (int) env('TELEMETRY_RETENTION_DAYS', 14)),

    // Default look-back window for the admin operational-telemetry overview.
    'overview_window_hours' => max(1, (int) env('TELEMETRY_OVERVIEW_WINDOW_HOURS', 24)),

    // Largest metadata payload (bytes) a single event may carry; larger is dropped.
    'max_metadata_bytes' => 4096,

    'http' => [
        'enabled' => (bool) env('TELEMETRY_HTTP_ENABLED', true),

        // A request slower than this is flagged as a slow sample in metadata.
        'slow_request_ms' => max(1, (int) env('TELEMETRY_SLOW_REQUEST_MS', 1000)),

        // Ascending upper-bound latency buckets (ms) for the cache histogram.
        // Percentiles are estimated from cumulative bucket counts.
        'buckets_ms' => [25, 50, 100, 250, 500, 1000, 2500, 5000, 10000],

        // How long (seconds) the rolling cache histogram/counters live.
        'window_seconds' => max(60, (int) env('TELEMETRY_HTTP_WINDOW_SECONDS', 3600)),
    ],

];
