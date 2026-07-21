<?php

declare(strict_types=1);

namespace Tests\Feature\Study;

use App\Domains\Intake\Enums\IntakeArtifactKind;
use App\Domains\Intake\Models\IntakeArtifact;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Study\Contracts\DocumentChatProvider;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

final class StudyChatOpenApiContractTest extends TestCase
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

    public function test_document_chat_matches_the_live_contract(): void
    {
        config()->set('ai.openai.api_key', 'test-key');
        config()->set('ai.models.document_chat', 'test-model');
        $this->app->instance(DocumentChatProvider::class, new class implements DocumentChatProvider
        {
            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'test-model';
            }

            public function reply(array $messages): string
            {
                return 'Section two defines the sampling frame as all enrolled students.';
            }
        });

        $owner = User::factory()->create();
        $item = $this->documentFor($owner);
        $this->actingAs($owner, 'web');

        $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/study/'.(string) $item->public_id.'/chat', [
                'message' => 'How is the sampling frame defined?',
                'history' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertOk()
            ->assertJsonPath('data.study_chat.model', 'test-model');
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'study-chat-contract-session');

        return $authenticatedRequest;
    }

    private function documentFor(User $owner): KnowledgeItem
    {
        $intakeItem = IntakeItem::factory()->extracted()->create(['user_id' => $owner->getKey()]);
        $text = 'Section two defines the sampling frame as all enrolled students.';

        IntakeArtifact::factory()->create([
            'intake_item_id' => $intakeItem->getKey(),
            'kind' => IntakeArtifactKind::ExtractedText->value,
            'content_type' => 'text/plain',
            'byte_size' => strlen($text),
            'text_content' => $text,
            'metadata' => ['characters' => mb_strlen($text)],
        ]);

        return KnowledgeItem::factory()
            ->fromIntake($intakeItem)
            ->create(['title' => 'Methods handout']);
    }

    /** @return array<string, string> */
    private function mutationHeaders(): array
    {
        return [
            ...$this->headers(),
            'X-XSRF-TOKEN' => 'study-chat-contract-test-token',
        ];
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
