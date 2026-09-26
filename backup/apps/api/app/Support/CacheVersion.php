<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A monotonically increasing per-domain version integer used to build cache
 * keys that self-invalidate: bumping the version orphans every key that
 * embedded the old one, so entries expire naturally without needing tag-aware
 * cache stores (the database store supports neither tags nor wildcard flush).
 */
final class CacheVersion
{
    private const PREFIX = 'cacheversion:';

    public function value(string $domain): int
    {
        try {
            return max(1, (int) Cache::store()->get($this->key($domain), 1));
        } catch (Throwable) {
            return 1;
        }
    }

    public function bump(string $domain): void
    {
        try {
            $store = Cache::store();

            if (! $store->add($this->key($domain), 2)) {
                $store->increment($this->key($domain));
            }
        } catch (Throwable) {
            // A cache hiccup must not break the write that triggered the bump.
        }
    }

    private function key(string $domain): string
    {
        return self::PREFIX.$domain;
    }
}
