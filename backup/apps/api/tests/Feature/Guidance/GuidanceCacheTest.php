<?php

declare(strict_types=1);

namespace Tests\Feature\Guidance;

use App\Domains\Guidance\Queries\BuildCategoryGuidance;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class GuidanceCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowserBoundary();
        config()->set('performance.guidance_cache.enabled', true);
    }

    public function test_cached_content_is_shared_but_viewer_state_stays_per_user(): void
    {
        $category = ToolCategory::factory()->create(['slug' => 'research-support']);
        $tool = Tool::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Alpha Tool',
        ]);
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        // Alice saves the tool through the real endpoint.
        $this->actingAs($alice, 'web');
        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tools/{$tool->public_id}/saved")
            ->assertOk();

        // The bundle content is cached on Alice's read; Bob's read hits the same
        // cached content but sees his own (empty) viewer state - no state leaks.
        $guidance = app(BuildCategoryGuidance::class);

        $forAlice = $guidance->execute($alice, 'research-support');
        self::assertSame(
            'saved',
            (string) $forAlice['tools']->firstOrFail()->getAttribute('viewer_preference_state'),
        );

        $forBob = $guidance->execute($bob, 'research-support');
        self::assertSame($tool->public_id, $forBob['tools']->firstOrFail()->public_id);
        self::assertNull($forBob['tools']->firstOrFail()->getAttribute('viewer_preference_state'));
    }

    public function test_curating_content_invalidates_the_cache(): void
    {
        $category = ToolCategory::factory()->create(['slug' => 'research-support']);
        Tool::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Alpha Tool',
        ]);
        $user = User::factory()->create();

        $this->actingAs($user, 'web');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/guidance?category=research-support')
            ->assertOk()
            ->assertJsonCount(1, 'data.tools');

        // Publishing more content bumps the content version, orphaning the cache.
        Tool::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Beta Tool',
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/guidance?category=research-support')
            ->assertOk()
            ->assertJsonCount(2, 'data.tools');
    }

    public function test_the_cache_hit_path_stays_query_bounded(): void
    {
        $category = ToolCategory::factory()->create(['slug' => 'research-support']);
        Tool::factory()->count(5)->published()->create(['tool_category_id' => $category->getKey()]);
        $user = User::factory()->create();

        // Warm the cache.
        $this->actingAs($user, 'web');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/guidance?category=research-support')
            ->assertOk();

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/guidance?category=research-support')
            ->assertOk()
            ->assertJsonCount(5, 'data.tools');

        // The cache hit skips the content queries; only auth + the six bounded
        // per-user viewer lookups remain.
        self::assertLessThanOrEqual(
            16,
            $queries,
            "The cached guidance read must stay bounded; observed {$queries} queries.",
        );
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'guidance-cache-test-token',
        ];
    }

    private function configureBrowserBoundary(): void
    {
        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }
}
