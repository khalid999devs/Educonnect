<?php

declare(strict_types=1);

namespace App\Domains\Tools\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Tools\Exceptions\ToolPersistenceFailure;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use App\Support\CacheVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * The tool category list is curated content: identical for every student, tiny,
 * and changing only through admin curation. It is therefore read in full (no
 * cursor) under a hard cap and cached under the shared content version that
 * curation bumps, exactly like BuildCategoryGuidance.
 */
final readonly class ListToolCategories
{
    /**
     * A hard ceiling so an unexpectedly large catalog can never produce an
     * unbounded payload. The seeded catalog target is well under this.
     */
    public const MAX_CATEGORIES = 100;

    public function __construct(private CacheVersion $cacheVersion) {}

    /** @return Collection<int, ToolCategory> */
    public function execute(User $user): Collection
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            return $this->categories();
        } catch (QueryException $exception) {
            throw ToolPersistenceFailure::fromQueryException($exception, 'tool.category.list');
        }
    }

    /** @return Collection<int, ToolCategory> */
    private function categories(): Collection
    {
        if (! (bool) config('performance.guidance_cache.enabled', true)) {
            return $this->fetchCategories();
        }

        $version = $this->cacheVersion->value('content');
        $ttl = max(1, (int) config('performance.guidance_cache.ttl_seconds', 300));

        return Cache::store()->remember(
            "tools:categories:v{$version}",
            $ttl,
            fn (): Collection => $this->fetchCategories(),
        );
    }

    /** @return Collection<int, ToolCategory> */
    private function fetchCategories(): Collection
    {
        return ToolCategory::query()
            ->orderBy('sort_order')
            ->orderByRaw('LOWER(tool_categories.name)')
            ->orderBy('public_id')
            ->limit(self::MAX_CATEGORIES)
            ->get();
    }
}
