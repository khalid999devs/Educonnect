<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Application caching (Phase 28)
    |--------------------------------------------------------------------------
    |
    | The published guidance bundle is expensive to assemble (visibility-filtered
    | reads across four catalogs with nested eager loads) yet changes only when
    | an administrator curates content. Its content is cached under a version key
    | that is bumped on any content mutation, so curation invalidates it
    | immediately; a TTL bounds staleness for scheduled visibility changes. The
    | cheap per-user viewer state is always applied fresh on top of the cache.
    |
    */

    'guidance_cache' => [
        'enabled' => (bool) env('GUIDANCE_CACHE_ENABLED', true),
        'ttl_seconds' => max(1, (int) env('GUIDANCE_CACHE_TTL_SECONDS', 300)),
    ],

];
