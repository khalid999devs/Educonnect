<?php

declare(strict_types=1);

namespace Tests\Feature\Guidance;

use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

final class GuidanceOpenApiContractTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }

    public function test_every_student_guidance_operation_matches_the_live_openapi_contract(): void
    {
        $user = User::factory()->create();
        $category = ToolCategory::factory()->create([
            'slug' => 'contract-guidance',
            'name' => 'Contract guidance',
            'description' => 'Contract-check category.',
        ]);
        $tool = Tool::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Contract Study Tool',
            'external_url' => 'https://tools.example.edu/contract-study-tool',
        ]);
        $prompt = PromptTemplate::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Contract Study Prompt',
        ]);
        $prompt->relatedTools()->attach($tool->getKey());
        $hiddenPrompt = PromptTemplate::factory()->create([
            'tool_category_id' => $category->getKey(),
        ]);
        $workflow = WorkflowRecipe::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Contract Study Workflow',
        ]);
        WorkflowStep::factory()->create([
            'workflow_recipe_id' => $workflow->getKey(),
            'tool_id' => $tool->getKey(),
            'prompt_template_id' => $prompt->getKey(),
            'destination_action' => 'use_tool',
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/prompts?search=Contract&category=contract-guidance&preference=all&sort=title&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $prompt->public_id);

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/prompts/{$prompt->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $prompt->public_id);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/prompts/{$prompt->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', true);

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/prompts/{$prompt->public_id}/saved")
            ->assertNoContent();

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/prompts/{$prompt->public_id}/dismissed")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.dismissed', true);

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/prompts/{$prompt->public_id}/dismissed")
            ->assertNoContent();

        $this->withHeaders($this->mutationHeaders())
            ->postJson("/api/v1/prompts/{$prompt->public_id}/copies")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.copy_count', 1);

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/workflows?search=Contract&category=contract-guidance&sort=title&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $workflow->public_id);

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/workflows/{$workflow->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $workflow->public_id)
            ->assertJsonPath('data.steps.0.tool.id', $tool->public_id)
            ->assertJsonPath('data.steps.0.prompt.id', $prompt->public_id);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/workflows/{$workflow->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', true);

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/workflows/{$workflow->public_id}/saved")
            ->assertNoContent();

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/workflows/{$workflow->public_id}/dismissed")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.dismissed', true);

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/workflows/{$workflow->public_id}/dismissed")
            ->assertNoContent();

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/guidance?category=contract-guidance')
            ->assertOk()
            ->assertJsonPath('data.category.key', 'contract-guidance')
            ->assertJsonCount(1, 'data.tools')
            ->assertJsonCount(1, 'data.prompts')
            ->assertJsonCount(1, 'data.workflows');

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/prompts/{$hiddenPrompt->public_id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->withoutRequestValidation()
            ->withHeaders($this->readHeaders())
            ->getJson('/api/v1/prompts?preference=unsupported&per_page=51')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withoutRequestValidation()
            ->withHeaders($this->readHeaders())
            ->getJson('/api/v1/guidance?category=Bad Slug')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withoutRequestValidation()
            ->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/workflows/{$workflow->public_id}/saved", ['unexpected' => true])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /** @return array<string, string> */
    private function mutationHeaders(): array
    {
        return [
            ...$this->readHeaders(),
            'X-XSRF-TOKEN' => 'guidance-contract-test-token',
        ];
    }

    /** @return array<string, string> */
    private function readHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'guidance-contract-session');

        return $authenticatedRequest;
    }
}
