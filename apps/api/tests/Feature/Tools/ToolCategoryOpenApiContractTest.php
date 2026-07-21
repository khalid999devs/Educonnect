<?php

declare(strict_types=1);

namespace Tests\Feature\Tools;

use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

final class ToolCategoryOpenApiContractTest extends TestCase
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
            'performance.guidance_cache.enabled' => false,
        ]);
    }

    public function test_the_tool_category_listing_matches_the_live_openapi_contract(): void
    {
        $user = User::factory()->create();
        ToolCategory::factory()->create([
            'slug' => 'contract-writing',
            'name' => 'Contract writing',
            'sort_order' => 1,
        ]);
        ToolCategory::factory()->create([
            'slug' => 'contract-research',
            'name' => 'Contract research',
            'sort_order' => 0,
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/tool-categories')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'contract-research')
            ->assertJsonPath('data.1.key', 'contract-writing');

        // The request allowlist is empty: every query parameter is unknown.
        $this->withoutRequestValidation()
            ->withHeaders($this->readHeaders())
            ->getJson('/api/v1/tool-categories?unexpected=true')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
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
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'tool-category-contract-session');

        return $authenticatedRequest;
    }
}
