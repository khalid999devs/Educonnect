<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Telemetry\Support\HttpMetricsStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records per-request latency into the rolling HTTP histogram after the
 * response has been sent (a terminable middleware), so the p50/p95/p99 shown in
 * the operational-telemetry view are measured, not guessed, and the recording
 * adds nothing to the user-perceived response time.
 */
final class RecordHttpMetrics
{
    private const START_KEY = 'telemetry.request_start_ns';

    public function __construct(private readonly HttpMetricsStore $store) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::START_KEY, hrtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! (bool) config('telemetry.http.enabled', true)) {
            return;
        }

        $path = $request->path();

        // Only real API endpoints; health probes are frequent and cheap and
        // would skew the latency distribution.
        if (! str_starts_with($path, 'api/') || str_starts_with($path, 'api/health') || str_starts_with($path, 'api/v1/health')) {
            return;
        }

        $start = $request->attributes->get(self::START_KEY);

        if (! is_int($start) && ! is_float($start)) {
            return;
        }

        $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
        $this->store->record($durationMs, $response->getStatusCode());
    }
}
