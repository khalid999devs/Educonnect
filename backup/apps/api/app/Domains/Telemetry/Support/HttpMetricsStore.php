<?php

declare(strict_types=1);

namespace App\Domains\Telemetry\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A rolling, store-agnostic HTTP request histogram kept in the cache. Writing a
 * durable row per request would add a write to the hot path (and pollute the
 * per-request query-count guards), so latency is bucketed into atomic cache
 * counters instead. Percentiles are honest bucket-boundary estimates over a
 * rolling window; the counters auto-expire, so the view is always recent.
 */
final class HttpMetricsStore
{
    private const PREFIX = 'telemetry:http:';

    /** Record one completed request. Best-effort; never throws to the caller. */
    public function record(int $durationMs, int $statusCode): void
    {
        if (! (bool) config('telemetry.http.enabled', true)) {
            return;
        }

        try {
            $ttl = max(60, (int) config('telemetry.http.window_seconds', 3600));
            $this->bump(self::PREFIX.'count', $ttl);

            if ($statusCode >= 500) {
                $this->bump(self::PREFIX.'errors', $ttl);
            }

            $this->bump(self::PREFIX.'bucket:'.$this->bucketFor($durationMs), $ttl);
        } catch (Throwable) {
            // Metrics are best-effort; a cache hiccup must not affect the response.
        }
    }

    /**
     * @return array{
     *     request_count: int,
     *     error_count: int,
     *     error_rate: float,
     *     latency_ms: array{p50: int|null, p95: int|null, p99: int|null},
     *     window_seconds: int
     * }
     */
    public function snapshot(): array
    {
        $count = $this->read(self::PREFIX.'count');
        $errors = $this->read(self::PREFIX.'errors');

        return [
            'request_count' => $count,
            'error_count' => $errors,
            'error_rate' => $count > 0 ? round($errors / $count, 4) : 0.0,
            'latency_ms' => [
                'p50' => $this->percentile(0.50),
                'p95' => $this->percentile(0.95),
                'p99' => $this->percentile(0.99),
            ],
            'window_seconds' => max(60, (int) config('telemetry.http.window_seconds', 3600)),
        ];
    }

    /** @return list<int> */
    private function buckets(): array
    {
        $buckets = config('telemetry.http.buckets_ms', [25, 50, 100, 250, 500, 1000, 2500, 5000, 10000]);

        return array_values(array_map(static fn ($value): int => (int) $value, is_array($buckets) ? $buckets : []));
    }

    private function bucketFor(int $durationMs): string
    {
        foreach ($this->buckets() as $index => $upperBound) {
            if ($durationMs <= $upperBound) {
                return (string) $index;
            }
        }

        return 'inf';
    }

    /** Estimate a percentile from cumulative bucket counts (upper-bound estimate). */
    private function percentile(float $quantile): ?int
    {
        $buckets = $this->buckets();
        $counts = [];
        $total = 0;

        foreach (array_keys($buckets) as $index) {
            $counts[$index] = $this->read(self::PREFIX.'bucket:'.$index);
            $total += $counts[$index];
        }
        $overflow = $this->read(self::PREFIX.'bucket:inf');
        $total += $overflow;

        if ($total === 0) {
            return null;
        }

        $threshold = $quantile * $total;
        $cumulative = 0;

        foreach ($buckets as $index => $upperBound) {
            $cumulative += $counts[$index];

            if ($cumulative >= $threshold) {
                return $upperBound;
            }
        }

        // The quantile lands in the overflow bucket (slower than the last bound).
        return null;
    }

    private function bump(string $key, int $ttl): void
    {
        $cache = $this->cache();

        if (! $cache->add($key, 1, $ttl)) {
            $cache->increment($key);
        }
    }

    private function read(string $key): int
    {
        return (int) $this->cache()->get($key, 0);
    }

    private function cache(): CacheRepository
    {
        return Cache::store();
    }
}
