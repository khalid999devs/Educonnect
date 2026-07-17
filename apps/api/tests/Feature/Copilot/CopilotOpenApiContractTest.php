<?php

declare(strict_types=1);

namespace Tests\Feature\Copilot;

use App\Domains\Copilot\Contracts\ChatProvider;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

final class CopilotOpenApiContractTest extends TestCase
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

    public function test_copilot_operations_match_the_live_contract(): void
    {
        config()->set('ai.openai.api_key', 'test-key');
        config()->set('ai.models.copilot', 'test-model');
        $this->app->instance(ChatProvider::class, new class implements ChatProvider
        {
            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'fake-model';
            }

            public function reply(array $messages): string
            {
                return 'An advisory reply.';
            }
        });

        $this->actingAs(User::factory()->create(), 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/copilot/availability')
            ->assertOk()
            ->assertJsonPath('data.copilot.enabled', true);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/copilot/messages', [
                'message' => 'What should I focus on today?',
                'history' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertOk()
            ->assertJsonPath('data.copilot.reply', 'An advisory reply.');
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'copilot-contract-session');

        return $authenticatedRequest;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }
}
