<?php

declare(strict_types=1);

namespace Tests\Feature\SecondBrain;

use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\ResearchTopic;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\SecondBrain\Concerns\InteractsWithSecondBrain;
use Tests\TestCase;

final class ResearchTopicTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithSecondBrain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_topic_lifecycle_tracks_keywords_and_versions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $created = $this->withHeaders($this->headers())
            ->postJson('/api/v1/research-topics', [
                'title' => '  Efficient attention mechanisms  ',
                'description' => 'Sub-quadratic attention variants.',
                'keywords' => [' linear attention ', 'sparse attention'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.title', 'Efficient attention mechanisms')
            ->assertJsonPath('data.keywords', ['linear attention', 'sparse attention'])
            ->assertJsonPath('data.source_count', 0);
        $topicId = $created->json('data.id');
        $this->assertIsString($topicId);

        $tooManyKeywords = $this->withHeaders($this->headers())
            ->postJson('/api/v1/research-topics', [
                'title' => 'Overloaded topic',
                'keywords' => array_map(static fn (int $i): string => "keyword-{$i}", range(1, 21)),
            ]);
        $this->assertApiError($tooManyKeywords, 422, ApiErrorCode::ValidationFailed);

        $updated = $this->withHeaders($this->headers())
            ->putJson("/api/v1/research-topics/{$topicId}", [
                'title' => 'Efficient attention mechanisms',
                'description' => 'Sub-quadratic attention variants and benchmarks.',
                'keywords' => ['linear attention'],
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.keywords', ['linear attention']);
        $updatedAt = $updated->json('data.updated_at');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/research-topics/{$topicId}", [
                'title' => 'Efficient attention mechanisms',
                'description' => 'Sub-quadratic attention variants and benchmarks.',
                'keywords' => ['linear attention'],
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.updated_at', $updatedAt);

        $stale = $this->withHeaders($this->headers())
            ->putJson("/api/v1/research-topics/{$topicId}", [
                'title' => 'Renamed topic',
                'keywords' => [],
                'expected_version' => 1,
            ]);
        $this->assertApiError($stale, 409, ApiErrorCode::Conflict);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/research-topics/{$topicId}", ['expected_version' => 2])
            ->assertNoContent();
        $this->assertDatabaseMissing('research_topics', ['public_id' => $topicId]);
    }

    public function test_sources_attach_with_reading_status_and_citation_visibility(): void
    {
        $user = User::factory()->create();
        $topic = ResearchTopic::factory()->for($user, 'user')->create();
        $paper = KnowledgeItem::factory()->for($user, 'user')->linkSource()->cited()->create([
            'title' => 'Attention Is All You Need',
        ]);
        $this->actingAs($user, 'web');

        $attached = $this->withHeaders($this->headers())
            ->postJson("/api/v1/research-topics/{$topic->public_id}/sources", [
                'knowledge_item_id' => (string) $paper->public_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.source_count', 1)
            ->assertJsonPath('data.sources.0.reading_status', 'to_read')
            ->assertJsonPath('data.sources.0.item.id', (string) $paper->public_id)
            ->assertJsonPath('data.sources.0.item.authors', 'Vaswani, A. and Shazeer, N.')
            ->assertJsonPath('data.sources.0.item.published_year', 2017)
            ->assertJsonPath('data.sources.0.item.venue', 'NeurIPS');
        self::assertIsArray($attached->json('data.sources'));

        $duplicate = $this->withHeaders($this->headers())
            ->postJson("/api/v1/research-topics/{$topic->public_id}/sources", [
                'knowledge_item_id' => (string) $paper->public_id,
                'reading_status' => 'reading',
            ]);
        $this->assertApiError($duplicate, 409, ApiErrorCode::Conflict);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/research-topics/{$topic->public_id}/sources/{$paper->public_id}", [
                'reading_status' => 'read',
            ])
            ->assertOk()
            ->assertJsonPath('data.sources.0.reading_status', 'read');

        $invalidStatus = $this->withHeaders($this->headers())
            ->putJson("/api/v1/research-topics/{$topic->public_id}/sources/{$paper->public_id}", [
                'reading_status' => 'skimmed',
            ]);
        $this->assertApiError($invalidStatus, 422, ApiErrorCode::ValidationFailed);

        $itemView = $this->withHeaders($this->headers())
            ->getJson("/api/v1/knowledge/{$paper->public_id}")
            ->assertOk();
        self::assertSame((string) $topic->public_id, $itemView->json('data.research_topics.0.id'));
        self::assertSame('read', $itemView->json('data.research_topics.0.reading_status'));

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/research-topics/{$topic->public_id}/sources/{$paper->public_id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('research_topic_sources', ['research_topic_id' => $topic->getKey()]);
        $this->assertDatabaseHas('knowledge_items', ['id' => $paper->getKey()]);

        $missingDetach = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/research-topics/{$topic->public_id}/sources/{$paper->public_id}");
        $this->assertApiError($missingDetach, 404, ApiErrorCode::ResourceNotFound);
    }

    public function test_foreign_knowledge_items_cannot_join_a_topic(): void
    {
        $user = User::factory()->create();
        $topic = ResearchTopic::factory()->for($user, 'user')->create();
        $foreignItem = KnowledgeItem::factory()->create();
        $this->actingAs($user, 'web');

        $denied = $this->withHeaders($this->headers())
            ->postJson("/api/v1/research-topics/{$topic->public_id}/sources", [
                'knowledge_item_id' => (string) $foreignItem->public_id,
            ]);
        $this->assertApiError($denied, 404, ApiErrorCode::ResourceNotFound);
        $this->assertDatabaseMissing('research_topic_sources', ['research_topic_id' => $topic->getKey()]);
    }
}
