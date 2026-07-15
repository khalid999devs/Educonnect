<?php

declare(strict_types=1);

namespace Tests\Feature\Guidance;

use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\UserWorkflowPreference;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkflowCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_only_reviewed_published_workflows_with_ordered_steps_are_visible(): void
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
            'state' => 'draft',
        ]);
        $publishedPrompt = PromptTemplate::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Summarize a Source',
        ]);
        $publishedPrompt->relatedTools()->attach($publishedTool->getKey());
        $draftPrompt = PromptTemplate::factory()->create([
            'tool_category_id' => $category->getKey(),
        ]);

        $reviewedAt = CarbonImmutable::parse('2026-07-10T08:00:00Z');
        $published = WorkflowRecipe::factory()->published($reviewedAt)->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Prepare a Literature Review',
            'goal' => 'Assemble a reviewed set of sources for one topic.',
            'expected_outcome' => 'An organized source list the student verified.',
            'integrity_note' => 'Verify every source and claim yourself before submission.',
            'provenance' => 'Reviewed against the related tool documentation.',
        ]);
        WorkflowStep::factory()->create([
            'workflow_recipe_id' => $published->getKey(),
            'step_number' => 1,
            'title' => 'Collect candidate sources',
            'instruction' => 'Gather sources from the course reading list and your library search.',
            'tool_id' => $publishedTool->getKey(),
            'destination_action' => 'use_tool',
        ]);
        WorkflowStep::factory()->create([
            'workflow_recipe_id' => $published->getKey(),
            'step_number' => 2,
            'title' => 'Summarize each source',
            'instruction' => 'Use the related prompt to draft a short summary you then verify.',
            'prompt_template_id' => $publishedPrompt->getKey(),
            'destination_action' => 'use_prompt',
        ]);
        WorkflowStep::factory()->create([
            'workflow_recipe_id' => $published->getKey(),
            'step_number' => 3,
            'title' => 'Plan the writing task',
            'instruction' => 'Create a planner task for the first draft deadline.',
            'tool_id' => $draftTool->getKey(),
            'prompt_template_id' => $draftPrompt->getKey(),
            'destination_action' => 'create_task',
        ]);

        $draft = WorkflowRecipe::factory()->create(['tool_category_id' => $category->getKey()]);
        $inReview = WorkflowRecipe::factory()->inReview()->create(['tool_category_id' => $category->getKey()]);
        $archived = WorkflowRecipe::factory()->archived()->create(['tool_category_id' => $category->getKey()]);
        WorkflowStep::factory()->create(['workflow_recipe_id' => $archived->getKey()]);
        $withoutSteps = WorkflowRecipe::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Published without steps',
        ]);
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/workflows')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->public_id)
            ->assertJsonPath('data.0.title', 'Prepare a Literature Review')
            ->assertJsonPath('data.0.category.key', 'research-support')
            ->assertJsonPath('data.0.goal', 'Assemble a reviewed set of sources for one topic.')
            ->assertJsonPath('data.0.expected_outcome', 'An organized source list the student verified.')
            ->assertJsonPath('data.0.integrity_note', 'Verify every source and claim yourself before submission.')
            ->assertJsonCount(3, 'data.0.steps')
            ->assertJsonPath('data.0.steps.0.number', 1)
            ->assertJsonPath('data.0.steps.0.destination_action', 'use_tool')
            ->assertJsonPath('data.0.steps.0.tool.id', $publishedTool->public_id)
            ->assertJsonPath('data.0.steps.0.tool.url', 'https://tools.example.edu/citation-helper')
            ->assertJsonPath('data.0.steps.1.number', 2)
            ->assertJsonPath('data.0.steps.1.destination_action', 'use_prompt')
            ->assertJsonPath('data.0.steps.1.prompt.id', $publishedPrompt->public_id)
            ->assertJsonPath('data.0.steps.1.prompt.title', 'Summarize a Source')
            ->assertJsonPath('data.0.steps.2.number', 3)
            ->assertJsonPath('data.0.steps.2.destination_action', 'create_task')
            ->assertJsonPath('data.0.steps.2.tool', null)
            ->assertJsonPath('data.0.steps.2.prompt', null)
            ->assertJsonPath('data.0.viewer_state.saved', false)
            ->assertJsonMissingPath('data.0.tool_category_id')
            ->assertJsonMissingPath('data.0.state');
        self::assertIsString($response->json('data.0.last_reviewed_at'));

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/workflows/{$published->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $published->public_id)
            ->assertJsonCount(3, 'data.steps');

        foreach ([$draft, $inReview, $archived, $withoutSteps] as $hiddenWorkflow) {
            $this->withHeaders($this->headers())
                ->getJson("/api/v1/workflows/{$hiddenWorkflow->public_id}")
                ->assertNotFound()
                ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        }
    }

    public function test_workflow_search_filters_and_preference_scopes_stay_bounded_and_private(): void
    {
        $user = User::factory()->create();
        $research = ToolCategory::factory()->create(['slug' => 'research', 'name' => 'Research']);
        $study = ToolCategory::factory()->create(['slug' => 'study-planning', 'name' => 'Study planning']);
        $baseReview = CarbonImmutable::parse('2026-07-10T08:00:00Z');

        $alpha = WorkflowRecipe::factory()->published($baseReview)->create([
            'tool_category_id' => $research->getKey(),
            'title' => 'Alpha Research Workflow',
        ]);
        $beta = WorkflowRecipe::factory()->published($baseReview->addDay())->create([
            'tool_category_id' => $research->getKey(),
            'title' => 'Beta Research Workflow',
        ]);
        $gamma = WorkflowRecipe::factory()->published($baseReview->addDays(2))->create([
            'tool_category_id' => $study->getKey(),
            'title' => 'Gamma Study Workflow',
        ]);

        foreach ([$alpha, $beta, $gamma] as $workflow) {
            WorkflowStep::factory()->create(['workflow_recipe_id' => $workflow->getKey()]);
        }

        UserWorkflowPreference::factory()->create([
            'user_id' => $user->getKey(),
            'workflow_recipe_id' => $alpha->getKey(),
            'state' => 'saved',
        ]);
        UserWorkflowPreference::factory()->create([
            'user_id' => $user->getKey(),
            'workflow_recipe_id' => $beta->getKey(),
            'state' => 'dismissed',
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/workflows?sort=title&per_page=50')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $alpha->public_id)
            ->assertJsonPath('data.1.id', $beta->public_id)
            ->assertJsonPath('data.2.id', $gamma->public_id);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/workflows?category=research&per_page=50')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/workflows?preference=saved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $alpha->public_id)
            ->assertJsonPath('data.0.viewer_state.saved', true);
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/workflows?preference=none&per_page=50')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $gamma->public_id);
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/workflows?search=Gamma')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $gamma->public_id);
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/workflows?sort=-last_reviewed_at&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $gamma->public_id);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/workflows?preference=unknown&per_page=51&unexpected=true')
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
            'X-XSRF-TOKEN' => 'workflow-catalog-test-token',
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
