<?php

declare(strict_types=1);

namespace Tests\Feature\Guidance;

use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class GuidanceEndpointTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_goal_endpoint_returns_a_complete_transparent_published_guidance_bundle(): void
    {
        $user = User::factory()->create();
        $category = ToolCategory::factory()->create([
            'slug' => 'research-support',
            'name' => 'Research support',
            'description' => 'Curated guidance for research work.',
        ]);
        $otherCategory = ToolCategory::factory()->create(['slug' => 'other-goals']);

        $publishedTool = Tool::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Citation Helper',
        ]);
        Tool::factory()->create([
            'tool_category_id' => $category->getKey(),
            'state' => 'draft',
        ]);
        Tool::factory()->published()->create([
            'tool_category_id' => $otherCategory->getKey(),
            'name' => 'Unrelated Tool',
        ]);

        $publishedPrompt = PromptTemplate::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Summarize a Source',
        ]);
        $publishedPrompt->relatedTools()->attach($publishedTool->getKey());
        PromptTemplate::factory()->create(['tool_category_id' => $category->getKey()]);

        $publishedWorkflow = WorkflowRecipe::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Prepare a Literature Review',
        ]);
        WorkflowStep::factory()->create([
            'workflow_recipe_id' => $publishedWorkflow->getKey(),
            'destination_action' => 'create_task',
        ]);
        WorkflowRecipe::factory()->create(['tool_category_id' => $category->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/guidance?category=research-support')
            ->assertOk()
            ->assertJsonPath('data.category.key', 'research-support')
            ->assertJsonPath('data.category.name', 'Research support')
            ->assertJsonPath('data.category.description', 'Curated guidance for research work.')
            ->assertJsonCount(1, 'data.tools')
            ->assertJsonPath('data.tools.0.id', $publishedTool->public_id)
            ->assertJsonCount(1, 'data.prompts')
            ->assertJsonPath('data.prompts.0.id', $publishedPrompt->public_id)
            ->assertJsonPath('data.prompts.0.related_tools.0.id', $publishedTool->public_id)
            ->assertJsonCount(1, 'data.workflows')
            ->assertJsonPath('data.workflows.0.id', $publishedWorkflow->public_id)
            ->assertJsonPath('data.workflows.0.steps.0.destination_action', 'create_task')
            ->assertJsonStructure(['meta' => ['request_id']]);

        $empty = $this->withHeaders($this->headers())
            ->getJson('/api/v1/guidance?category=other-goals')
            ->assertOk()
            ->assertJsonCount(1, 'data.tools')
            ->assertJsonCount(0, 'data.prompts')
            ->assertJsonCount(0, 'data.workflows');
        self::assertSame('other-goals', $empty->json('data.category.key'));
    }

    public function test_goal_endpoint_bounds_collections_and_conceals_unknown_categories(): void
    {
        $user = User::factory()->create();
        $category = ToolCategory::factory()->create(['slug' => 'busy-category']);

        Tool::factory()->published()->count(12)->create([
            'tool_category_id' => $category->getKey(),
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/guidance?category=busy-category')
            ->assertOk()
            ->assertJsonCount(10, 'data.tools');

        $missing = $this->withHeaders($this->headers())
            ->getJson('/api/v1/guidance?category=unknown-category');
        $this->assertApiError($missing, 404, ApiErrorCode::ResourceNotFound);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/guidance')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/guidance?category=Invalid Slug&unexpected=true')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => [
                'category',
                'unexpected',
            ]]]]);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'guidance-endpoint-test-token',
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
