<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A minimal cache-backed circuit breaker. After a run of consecutive failures
 * the breaker opens for a cooldown window (a self-expiring cache key), during
 * which callers should skip the protected dependency instead of hammering it.
 * State is shared across workers via the default cache store; a failing store
 * fails open (the breaker never blocks traffic on its own outage).
 */
final class CircuitBreaker
{
    private const PREFIX = 'circuit:';

    public function isAvailable(string $key): bool
    {
        try {
            return ! Cache::store()->has($this->openKey($key));
        } catch (Throwable) {
            return true;
        }
    }

    public function recordSuccess(string $key): void
    {
        try {
            Cache::store()->forget($this->failureKey($key));
            Cache::store()->forget($this->openKey($key));
        } catch (Throwable) {
            // A cache hiccup must not surface to the protected call site.
        }
    }

    /**
     * Record a failure; once `threshold` consecutive failures accrue, open the
     * breaker for `cooldownSeconds`.
     */
    public function recordFailure(string $key, int $threshold, int $cooldownSeconds): void
    {
        $threshold = max(1, $threshold);
        $cooldownSeconds = max(1, $cooldownSeconds);

        try {
            $failures = (int) Cache::store()->get($this->failureKey($key), 0) + 1;

            if ($failures >= $threshold) {
                Cache::store()->put($this->openKey($key), 1, $cooldownSeconds);
                Cache::store()->forget($this->failureKey($key));

                return;
            }

            Cache::store()->put($this->failureKey($key), $failures, max(60, $cooldownSeconds));
        } catch (Throwable) {
            // Failing to record must not surface to the protected call site.
        }
    }

    private function openKey(string $key): string
    {
        return self::PREFIX.$key.':open';
    }

    private function failureKey(string $key): string
    {
        return self::PREFIX.$key.':failures';
    }
}
