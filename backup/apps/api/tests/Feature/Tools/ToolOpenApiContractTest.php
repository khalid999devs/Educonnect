<?php

declare(strict_types=1);

namespace Tests\Feature\Tools;

use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

final class ToolOpenApiContractTest extends TestCase
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

    public function test_every_student_tool_operation_matches_the_live_openapi_contract(): void
    {
        $user = User::factory()->create();
        $category = ToolCategory::factory()->create([
            'slug' => 'contract-tools',
            'name' => 'Contract tools',
        ]);
        $tool = Tool::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Contract Study Tool',
            'external_url' => 'https://tools.example.edu/contract-study-tool',
        ]);
        $hidden = Tool::factory()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Hidden Contract Draft',
            'state' => 'draft',
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/tools?search=Contract&category=contract-tools&preference=all&sort=name&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tool->public_id);

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/tools/{$tool->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $tool->public_id);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/tools/{$tool->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', true)
            ->assertJsonPath('data.viewer_state.dismissed', false);

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/tools/{$tool->public_id}/saved")
            ->assertNoContent();

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/tools/{$tool->public_id}/dismissed")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', false)
            ->assertJsonPath('data.viewer_state.dismissed', true);

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/tools/{$tool->public_id}/dismissed")
            ->assertNoContent();

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/tools/{$hidden->public_id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->withoutRequestValidation()
            ->withHeaders($this->readHeaders())
            ->getJson('/api/v1/tools?preference=unsupported&per_page=51')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withoutRequestValidation()
            ->withHeaders($this->readHeaders())
            ->getJson("/api/v1/tools/{$tool->public_id}?unexpected=true")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withoutRequestValidation()
            ->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/tools/{$tool->public_id}/saved", ['unexpected' => true])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /** @return array<string, string> */
    private function mutationHeaders(): array
    {
        return [
            ...$this->readHeaders(),
            'X-XSRF-TOKEN' => 'tool-contract-test-token',
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
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'tool-contract-session');

        return $authenticatedRequest;
    }
}
