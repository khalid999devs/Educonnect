<?php

declare(strict_types=1);

namespace Tests\Feature\SecondBrain;

use App\Domains\Resources\Models\Resource;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\ResearchTopic;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Feature\SecondBrain\Concerns\InteractsWithSecondBrain;
use Tests\TestCase;

final class SecondBrainOpenApiContractTest extends TestCase
{
    use InteractsWithSecondBrain;
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_every_second_brain_operation_matches_the_live_openapi_contract(): void
    {
        $user = User::factory()->create();
        $resource = Resource::factory()->for($user, 'user')->link('https://example.edu/reading')->create();
        $this->actingAs($user, 'web');

        // Collections.
        $collectionId = $this->withHeaders($this->headers())
            ->postJson('/api/v1/collections', [
                'name' => 'Contract research shelf',
                'description' => 'Contract test collection.',
                'kind' => 'research',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/collections?kind=research&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/collections/{$collectionId}")
            ->assertOk()
            ->assertJsonPath('data.id', $collectionId);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/collections/{$collectionId}", [
                'name' => 'Contract research shelf',
                'description' => 'Updated contract description.',
                'kind' => 'research',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        // Knowledge items.
        $itemId = $this->withHeaders($this->headers())
            ->postJson('/api/v1/knowledge', [
                'title' => 'Contract knowledge item',
                'summary' => 'A resource-backed source.',
                'source_type' => 'resource',
                'resource_id' => (string) $resource->public_id,
                'authors' => 'Contract, A.',
                'published_year' => 2024,
                'venue' => 'Contract Conf',
                'doi' => '10.1000/contract.1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.source.type', 'resource')
            ->json('data.id');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$itemId}", [
                'title' => 'Contract knowledge item',
                'summary' => 'A resource-backed primary source.',
                'authors' => 'Contract, A.',
                'published_year' => 2024,
                'venue' => 'Contract Conf',
                'doi' => '10.1000/contract.1',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$itemId}/tags", ['tags' => ['contract', 'testing']])
            ->assertOk()
            ->assertJsonPath('data.tags.0', 'contract');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$itemId}/collections", ['collection_ids' => [$collectionId]])
            ->assertOk()
            ->assertJsonPath('data.collections.0.id', $collectionId);

        $noteId = $this->withHeaders($this->headers())
            ->postJson("/api/v1/knowledge/{$itemId}/notes", ['body' => 'Contract note body.'])
            ->assertCreated()
            ->json('data.id');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$itemId}/notes/{$noteId}", [
                'body' => 'Contract note body, revised.',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $target = KnowledgeItem::factory()->for($user, 'user')->create(['title' => 'Contract link target']);
        $linkId = $this->withHeaders($this->headers())
            ->postJson("/api/v1/knowledge/{$itemId}/links", [
                'target_id' => (string) $target->public_id,
                'relation_type' => 'supports',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/knowledge?search=contract&tag=contract&source_type=resource&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/knowledge/{$itemId}")
            ->assertOk()
            ->assertJsonPath('data.id', $itemId)
            ->assertJsonPath('data.notes.0.id', $noteId);

        // Research topics.
        $topicId = $this->withHeaders($this->headers())
            ->postJson('/api/v1/research-topics', [
                'title' => 'Contract research topic',
                'description' => 'Reading list for the contract.',
                'keywords' => ['contracts', 'testing'],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/research-topics/{$topicId}", [
                'title' => 'Contract research topic',
                'description' => 'Reading list for the live contract.',
                'keywords' => ['contracts'],
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/research-topics/{$topicId}/sources", [
                'knowledge_item_id' => $itemId,
                'reading_status' => 'reading',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sources.0.reading_status', 'reading');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/research-topics/{$topicId}/sources/{$itemId}", ['reading_status' => 'read'])
            ->assertOk()
            ->assertJsonPath('data.sources.0.reading_status', 'read');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/research-topics?search=contract&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/research-topics/{$topicId}")
            ->assertOk()
            ->assertJsonPath('data.source_count', 1);

        // Destructive paths.
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/research-topics/{$topicId}/sources/{$itemId}")
            ->assertNoContent();
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/knowledge/{$itemId}/links/{$linkId}")
            ->assertNoContent();
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/knowledge/{$itemId}/notes/{$noteId}", ['expected_version' => 2])
            ->assertNoContent();
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/research-topics/{$topicId}", ['expected_version' => 2])
            ->assertNoContent();
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/knowledge/{$itemId}", ['expected_version' => 2])
            ->assertNoContent();
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/collections/{$collectionId}", ['expected_version' => 2])
            ->assertNoContent();

        // Invalid inputs still produce the concealed error contract.
        $this->withoutRequestValidation()
            ->withHeaders($this->headers())
            ->postJson('/api/v1/knowledge', [
                'title' => 'Bad source',
                'source_type' => 'link',
                'source_url' => 'http://insecure.example/doc',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withoutRequestValidation()
            ->withHeaders($this->readHeaders())
            ->getJson('/api/v1/knowledge?source_type=unsupported&per_page=51')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_empty_second_brain_states_match_the_contract(): void
    {
        $user = User::factory()->create();
        ResearchTopic::factory()->for($user, 'user')->create(['title' => 'Lonely topic']);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/collections')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.summary.total', 0);

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/knowledge')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/research-topics')
            ->assertOk()
            ->assertJsonPath('data.0.source_count', 0);
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
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'second-brain-contract-session');

        return $authenticatedRequest;
    }
}
