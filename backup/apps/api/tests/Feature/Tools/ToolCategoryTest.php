<?php

declare(strict_types=1);

namespace Tests\Feature\Tools;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class ToolCategoryTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
        config()->set('performance.guidance_cache.enabled', true);
    }

    public function test_every_category_is_returned_ordered_by_sort_order_then_name(): void
    {
        $user = User::factory()->create();

        ToolCategory::factory()->create([
            'slug' => 'writing-support',
            'name' => 'Writing support',
            'description' => 'Drafting and revision tools.',
            'sort_order' => 20,
        ]);
        ToolCategory::factory()->create([
            'slug' => 'beta-support',
            'name' => 'Beta support',
            'sort_order' => 10,
        ]);
        ToolCategory::factory()->create([
            'slug' => 'alpha-support',
            'name' => 'Alpha support',
            'sort_order' => 10,
        ]);

        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->headers())->getJson('/api/v1/tool-categories');

        $response->assertOk()->assertJsonCount(3, 'data');

        self::assertSame(
            ['alpha-support', 'beta-support', 'writing-support'],
            array_column((array) $response->json('data'), 'key'),
        );
    }

    public function test_a_category_exposes_its_curated_summary_fields(): void
    {
        $user = User::factory()->create();
        $category = ToolCategory::factory()->create([
            'slug' => 'research-support',
            'name' => 'Research support',
            'description' => 'Curated tools for finding and checking sources.',
            'sort_order' => 5,
        ]);

        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tool-categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $category->public_id)
            ->assertJsonPath('data.0.key', 'research-support')
            ->assertJsonPath('data.0.name', 'Research support')
            ->assertJsonPath('data.0.description', 'Curated tools for finding and checking sources.')
            ->assertJsonPath('data.0.sort_order', 5);
    }

    public function test_an_empty_catalog_returns_an_empty_collection_rather_than_an_error(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tool-categories')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_curating_a_category_invalidates_the_cached_list(): void
    {
        $user = User::factory()->create();
        ToolCategory::factory()->create([
            'slug' => 'alpha-support',
            'name' => 'Alpha support',
            'sort_order' => 10,
        ]);

        $this->actingAs($user, 'web');

        // Warms the cache under the current content version.
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tool-categories')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Creating a category bumps the shared content version, orphaning the
        // key the previous read wrote (see CacheVersion).
        ToolCategory::factory()->create([
            'slug' => 'beta-support',
            'name' => 'Beta support',
            'sort_order' => 20,
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tool-categories')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Renaming an existing category invalidates it just as reliably.
        $renamed = ToolCategory::query()->where('slug', 'alpha-support')->firstOrFail();
        $renamed->forceFill(['name' => 'Alpha support renamed'])->save();

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tool-categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha support renamed');
    }

    public function test_a_warm_cache_serves_the_list_without_touching_the_categories_table(): void
    {
        $user = User::factory()->create();
        ToolCategory::factory()->count(3)->create();

        $this->actingAs($user, 'web');
        $this->withHeaders($this->headers())->getJson('/api/v1/tool-categories')->assertOk();

        $selects = 0;
        DB::listen(static function (QueryExecuted $query) use (&$selects): void {
            if (str_contains($query->sql, 'from "tool_categories"')) {
                $selects++;
            }
        });

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tool-categories')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        self::assertSame(0, $selects);
    }

    public function test_unknown_query_parameters_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tool-categories?per_page=5');

        $this->assertApiError($response, 422, ApiErrorCode::ValidationFailed);
    }

    public function test_the_endpoint_requires_an_authenticated_verified_capable_session(): void
    {
        $guest = $this->withHeaders($this->headers())->getJson('/api/v1/tool-categories');
        $this->assertApiError($guest, 401, ApiErrorCode::AuthenticationRequired);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web');
        $unverifiedResponse = $this->withHeaders($this->headers())->getJson('/api/v1/tool-categories');
        $this->assertApiError($unverifiedResponse, 403, ApiErrorCode::AuthorizationDenied);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        DB::table('role_capability')
            ->where('role_id', DB::table('roles')->where('key', RoleKey::Student->value)->value('id'))
            ->where('capability_id', DB::table('capabilities')
                ->where('key', CapabilityKey::AcademicManageOwn->value)
                ->value('id'))
            ->delete();

        $capabilityDenied = $this->withHeaders($this->headers())->getJson('/api/v1/tool-categories');
        $this->assertApiError($capabilityDenied, 403, ApiErrorCode::AuthorizationDenied);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'tool-category-test-token',
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
