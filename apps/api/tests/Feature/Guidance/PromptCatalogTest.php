<?php

declare(strict_types=1);

namespace Tests\Feature\Guidance;

use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\UserPromptPreference;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Tests\TestCase;

final class PromptCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_only_reviewed_published_prompts_with_related_tools_are_visible(): void
    {
        $user = User::factory()->create();
        $category = ToolCategory::factory()->create([
            'slug' => 'research-support',
            'name' => 'Research support',
        ]);
        $publishedTool = Tool::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Citation Helper',
            'external_url' => 'https://tools.example.edu/citation-helper',
        ]);
        $draftTool = Tool::factory()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Draft Tool',
            'state' => 'draft',
        ]);
        $reviewedAt = CarbonImmutable::parse('2026-07-10T08:00:00Z');
        $published = PromptTemplate::factory()->published($reviewedAt)->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Summarize a Source',
            'purpose' => 'Structure one bounded summarization request.',
            'template_body' => 'Summarize {{source_title}} focusing on {{focus_area}}.',
            'placeholders' => ['source_title', 'focus_area'],
            'expected_output' => 'A short structured summary with key claims.',
            'integrity_note' => 'Review every generated summary before academic use.',
            'provenance' => 'Reviewed against the related tool documentation.',
        ]);
        $published->relatedTools()->attach([$publishedTool->getKey(), $draftTool->getKey()]);

        $draft = PromptTemplate::factory()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Draft prompt',
        ]);
        $inReview = PromptTemplate::factory()->inReview()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Prompt under review',
        ]);
        $archived = PromptTemplate::factory()->archived()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Archived prompt',
        ]);
        $archived->relatedTools()->attach($publishedTool->getKey());
        $withoutTools = PromptTemplate::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Published without related tools',
        ]);
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/prompts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->public_id)
            ->assertJsonPath('data.0.title', 'Summarize a Source')
            ->assertJsonPath('data.0.category.key', 'research-support')
            ->assertJsonPath('data.0.template_body', 'Summarize {{source_title}} focusing on {{focus_area}}.')
            ->assertJsonPath('data.0.placeholders.0', 'source_title')
            ->assertJsonPath('data.0.placeholders.1', 'focus_area')
            ->assertJsonPath('data.0.expected_output', 'A short structured summary with key claims.')
            ->assertJsonPath('data.0.integrity_note', 'Review every generated summary before academic use.')
            ->assertJsonPath('data.0.provenance', 'Reviewed against the related tool documentation.')
            ->assertJsonCount(1, 'data.0.related_tools')
            ->assertJsonPath('data.0.related_tools.0.id', $publishedTool->public_id)
            ->assertJsonPath('data.0.related_tools.0.url', 'https://tools.example.edu/citation-helper')
            ->assertJsonPath('data.0.viewer_state.saved', false)
            ->assertJsonPath('data.0.viewer_state.dismissed', false)
            ->assertJsonPath('data.0.viewer_state.copy_count', 0)
            ->assertJsonMissingPath('data.0.tool_category_id')
            ->assertJsonMissingPath('data.0.state')
            ->assertJsonMissingPath('data.0.version');
        self::assertIsString($response->json('data.0.last_reviewed_at'));

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/prompts/{$published->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $published->public_id)
            ->assertJsonCount(1, 'data.related_tools');

        foreach ([$draft, $inReview, $archived, $withoutTools] as $hiddenPrompt) {
            $this->withHeaders($this->headers())
                ->getJson("/api/v1/prompts/{$hiddenPrompt->public_id}")
                ->assertNotFound()
                ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        }
    }

    public function test_prompt_search_filters_sorts_and_cursor_pages_are_bounded_and_private(): void
    {
        $user = User::factory()->create();
        $research = ToolCategory::factory()->create(['slug' => 'research', 'name' => 'Research']);
        $study = ToolCategory::factory()->create(['slug' => 'study-planning', 'name' => 'Study planning']);
        $relatedTool = Tool::factory()->published()->create();
        $baseReview = CarbonImmutable::parse('2026-07-10T08:00:00Z');

        $alpha = PromptTemplate::factory()->published($baseReview)->create([
            'tool_category_id' => $research->getKey(),
            'title' => 'Alpha Research Prompt',
        ]);
        $beta = PromptTemplate::factory()->published($baseReview->addDay())->create([
            'tool_category_id' => $research->getKey(),
            'title' => 'Beta Research Prompt',
        ]);
        $literal = PromptTemplate::factory()->published($baseReview->addDays(2))->create([
            'tool_category_id' => $study->getKey(),
            'title' => '100%_Study Prompt',
        ]);
        $gamma = PromptTemplate::factory()->published($baseReview->addDays(3))->create([
            'tool_category_id' => $study->getKey(),
            'title' => 'Gamma Study Prompt',
        ]);

        foreach ([$alpha, $beta, $literal, $gamma] as $prompt) {
            $prompt->relatedTools()->attach($relatedTool->getKey());
        }

        UserPromptPreference::factory()->create([
            'user_id' => $user->getKey(),
            'prompt_template_id' => $alpha->getKey(),
            'state' => 'saved',
        ]);
        UserPromptPreference::factory()->create([
            'user_id' => $user->getKey(),
            'prompt_template_id' => $beta->getKey(),
            'state' => 'dismissed',
        ]);
        $this->actingAs($user, 'web');

        $firstPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/prompts?sort=title&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.per_page', 2);
        $cursor = $firstPage->json('meta.pagination.next_cursor');
        self::assertIsString($cursor);
        $decoded = Cursor::fromEncoded($cursor);
        self::assertInstanceOf(Cursor::class, $decoded);
        self::assertSame(
            ['prompt_title_asc', 'public_id', '_pointsToNextItems'],
            array_keys($decoded->toArray()),
        );
        $cursorJson = json_encode($decoded->toArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('template_body', $cursorJson);
        self::assertStringNotContainsString('tool_category_id', $cursorJson);

        $secondPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/prompts?sort=title&per_page=2&cursor='.rawurlencode($cursor))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.next_cursor', null);
        $listedTitles = [...$firstPage->json('data.*.title'), ...$secondPage->json('data.*.title')];
        $expectedTitles = [$alpha->title, $beta->title, $literal->title, $gamma->title];
        sort($expectedTitles, SORT_STRING);
        self::assertSame($expectedTitles, $listedTitles);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/prompts?category=research&preference=all&per_page=50')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/prompts?preference=saved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $alpha->public_id)
            ->assertJsonPath('data.0.viewer_state.saved', true);
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/prompts?preference=dismissed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $beta->public_id);
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/prompts?preference=none&per_page=50')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/prompts?search='.rawurlencode('100%_'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $literal->public_id);
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/prompts?sort=-last_reviewed_at&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $gamma->public_id);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/prompts?sort=-last_reviewed_at&cursor='.rawurlencode($cursor))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/prompts?preference=unknown&per_page=51&unexpected=true')
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
            'X-XSRF-TOKEN' => 'prompt-catalog-test-token',
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
