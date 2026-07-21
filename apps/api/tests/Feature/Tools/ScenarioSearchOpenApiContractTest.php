<?php

declare(strict_types=1);

namespace Tests\Feature\Tools;

use App\Domains\Tools\AI\DeterministicScenarioRanker;
use App\Domains\Tools\Contracts\ScenarioRanker;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

final class ScenarioSearchOpenApiContractTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    private const ENDPOINT = '/api/v1/tools/scenario-search';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
            'ai.openai.api_key' => null,
            'ai.features.tool_scenario.enabled' => true,
        ]);

        // No key configured, so the deterministic ranker is the bound primary -
        // exactly the AppServiceProvider behaviour, and no network in a contract test.
        $this->app->bind(ScenarioRanker::class, DeterministicScenarioRanker::class);
        Http::fake();
    }

    public function test_the_scenario_search_response_matches_the_live_openapi_contract(): void
    {
        $user = User::factory()->create();
        Tool::factory()
            ->for(ToolCategory::factory()->create(['slug' => 'contract-revision', 'name' => 'Contract revision']), 'category')
            ->published()
            ->create(['name' => 'Contract Revision Planner']);

        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson(self::ENDPOINT, ['scenario' => 'I have an exam in 3 days', 'category' => 'contract-revision'])
            ->assertOk()
            ->assertJsonPath('data.scenario_search.ai_ranked', false)
            ->assertJsonCount(1, 'data.scenario_search.results');
    }

    public function test_an_empty_result_set_still_matches_the_contract(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson(self::ENDPOINT, ['scenario' => 'I have an exam in 3 days'])
            ->assertOk()
            ->assertJsonCount(0, 'data.scenario_search.results');
    }

    public function test_an_unknown_field_is_rejected_before_any_ranking_happens(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->withoutRequestValidation()
            ->withHeaders($this->headers())
            ->postJson(self::ENDPOINT, ['scenario' => 'I have an exam in 3 days', 'unexpected' => true])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        Http::assertNothingSent();
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'scenario-search-contract-token',
        ];
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'scenario-search-contract-session');

        return $authenticatedRequest;
    }
}
