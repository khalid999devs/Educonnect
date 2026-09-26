<?php

declare(strict_types=1);

namespace Tests\Feature\Tools;

use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Tools\Models\UserToolPreference;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Tests\TestCase;

final class ToolCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_only_reviewed_published_tools_are_visible_with_transparent_metadata(): void
    {
        $user = User::factory()->create();
        $category = ToolCategory::factory()->create([
            'slug' => 'research-support',
            'name' => 'Research support',
        ]);
        $reviewedAt = CarbonImmutable::parse('2026-07-10T08:00:00Z');
        $published = Tool::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Citation Helper',
            'purpose' => 'Organize citation details before writing.',
            'selection_reason' => 'Useful when a student needs a repeatable citation workflow.',
            'use_cases' => ['Capture source metadata', 'Export a bibliography draft'],
            'usage_guidance' => 'Verify every generated citation against the original source.',
            'limitations' => 'Imported metadata may be incomplete or incorrect.',
            'cost_note' => 'A free tier is available; paid limits may change.',
            'privacy_note' => 'Review provider retention settings before uploading private material.',
            'external_url' => 'https://tools.example.edu/citation-helper',
            'provenance' => 'Reviewed against the provider documentation.',
            'last_reviewed_at' => $reviewedAt,
            'published_at' => $reviewedAt->addHour(),
        ]);
        $draft = Tool::factory()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Draft guidance',
            'state' => 'draft',
        ]);
        $inReview = Tool::factory()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Guidance under review',
            'state' => 'in_review',
        ]);
        $archived = Tool::factory()->archived($reviewedAt->addDays(2))->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Archived guidance',
        ]);
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tools')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->public_id)
            ->assertJsonPath('data.0.name', 'Citation Helper')
            ->assertJsonPath('data.0.category.key', 'research-support')
            ->assertJsonPath('data.0.category.name', 'Research support')
            ->assertJsonPath('data.0.purpose', 'Organize citation details before writing.')
            ->assertJsonPath(
                'data.0.selection_reason',
                'Useful when a student needs a repeatable citation workflow.',
            )
            ->assertJsonPath('data.0.use_cases.0', 'Capture source metadata')
            ->assertJsonPath('data.0.use_cases.1', 'Export a bibliography draft')
            ->assertJsonPath(
                'data.0.usage_guidance',
                'Verify every generated citation against the original source.',
            )
            ->assertJsonPath('data.0.limitations', 'Imported metadata may be incomplete or incorrect.')
            ->assertJsonPath('data.0.cost_note', 'A free tier is available; paid limits may change.')
            ->assertJsonPath(
                'data.0.privacy_note',
                'Review provider retention settings before uploading private material.',
            )
            ->assertJsonPath('data.0.url', 'https://tools.example.edu/citation-helper')
            ->assertJsonPath('data.0.provenance', 'Reviewed against the provider documentation.')
            ->assertJsonPath('data.0.viewer_state.saved', false)
            ->assertJsonPath('data.0.viewer_state.dismissed', false)
            ->assertJsonMissingPath('data.0.tool_category_id')
            ->assertJsonMissingPath('data.0.state')
            ->assertJsonMissingPath('data.0.version')
            ->assertJsonMissingPath('data.0.published_at')
            ->assertJsonMissingPath('data.0.archived_at');
        self::assertIsString($response->json('data.0.last_reviewed_at'));

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/tools/{$published->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $published->public_id)
            ->assertJsonPath('data.viewer_state.saved', false)
            ->assertJsonPath('data.viewer_state.dismissed', false);

        foreach ([$draft, $inReview, $archived] as $hiddenTool) {
            $this->withHeaders($this->headers())
                ->getJson("/api/v1/tools/{$hiddenTool->public_id}")
                ->assertNotFound()
                ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        }
    }

    public function test_catalog_search_filters_sorts_and_cursor_pages_are_bounded_and_private(): void
    {
        $user = User::factory()->create();
        $research = ToolCategory::factory()->create([
            'slug' => 'research',
            'name' => 'Research',
            'sort_order' => 10,
        ]);
        $study = ToolCategory::factory()->create([
            'slug' => 'study-planning',
            'name' => 'Study planning',
            'sort_order' => 20,
        ]);
        $baseReview = CarbonImmutable::parse('2026-07-10T08:00:00Z');
        $alpha = Tool::factory()->published()->create([
            'tool_category_id' => $research->getKey(),
            'name' => 'Alpha Research Tool',
            'external_url' => 'https://tools.example.edu/private-alpha',
            'last_reviewed_at' => $baseReview,
            'published_at' => $baseReview->addHour(),
        ]);
        $beta = Tool::factory()->published()->create([
            'tool_category_id' => $research->getKey(),
            'name' => 'Beta Research Tool',
            'last_reviewed_at' => $baseReview->addDay(),
            'published_at' => $baseReview->addDay()->addHour(),
        ]);
        $literal = Tool::factory()->published()->create([
            'tool_category_id' => $study->getKey(),
            'name' => '100%_Study Planner',
            'last_reviewed_at' => $baseReview->addDays(2),
            'published_at' => $baseReview->addDays(2)->addHour(),
        ]);
        $gamma = Tool::factory()->published()->create([
            'tool_category_id' => $study->getKey(),
            'name' => 'Gamma Study Tool',
            'last_reviewed_at' => $baseReview->addDays(3),
            'published_at' => $baseReview->addDays(3)->addHour(),
        ]);
        UserToolPreference::factory()->create([
            'user_id' => $user->getKey(),
            'tool_id' => $alpha->getKey(),
            'state' => 'saved',
        ]);
        UserToolPreference::factory()->create([
            'user_id' => $user->getKey(),
            'tool_id' => $beta->getKey(),
            'state' => 'dismissed',
        ]);
        $this->actingAs($user, 'web');

        $firstPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tools?sort=name&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.per_page', 2);
        $cursor = $firstPage->json('meta.pagination.next_cursor');
        self::assertIsString($cursor);
        self::assertIsString($firstPage->json('links.next'));
        $decoded = Cursor::fromEncoded($cursor);
        self::assertInstanceOf(Cursor::class, $decoded);
        self::assertSame(
            ['tool_name_asc', 'public_id', '_pointsToNextItems'],
            array_keys($decoded->toArray()),
        );
        $cursorJson = json_encode($decoded->toArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('private-alpha', $cursorJson);
        self::assertStringNotContainsString('purpose', $cursorJson);
        self::assertStringNotContainsString('tool_category_id', $cursorJson);

        $secondPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tools?sort=name&per_page=2&cursor='.rawurlencode($cursor))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.next_cursor', null);
        $listedNames = [...$firstPage->json('data.*.name'), ...$secondPage->json('data.*.name')];
        $expectedNames = [$alpha->name, $beta->name, $literal->name, $gamma->name];
        sort($expectedNames, SORT_STRING);
        self::assertSame($expectedNames, $listedNames);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tools?category=research&preference=all&per_page=50')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tools?preference=saved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $alpha->public_id)
            ->assertJsonPath('data.0.viewer_state.saved', true)
            ->assertJsonPath('data.0.viewer_state.dismissed', false);
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tools?preference=dismissed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $beta->public_id)
            ->assertJsonPath('data.0.viewer_state.saved', false)
            ->assertJsonPath('data.0.viewer_state.dismissed', true);
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tools?preference=none&per_page=50')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tools?search='.rawurlencode('100%_'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $literal->public_id);
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tools?sort=-last_reviewed_at&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $gamma->public_id);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tools?sort=-last_reviewed_at&cursor='.rawurlencode($cursor))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tools?preference=unknown&per_page=51&unexpected=true')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => [
                'preference',
                'per_page',
                'unexpected',
            ]]]]);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'tool-catalog-test-token',
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
