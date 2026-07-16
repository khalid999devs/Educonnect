<?php

declare(strict_types=1);

namespace Tests\Feature\SecondBrain;

use App\Domains\Resources\Models\Resource;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\KnowledgeNote;
use App\Domains\SecondBrain\Models\KnowledgeTag;
use App\Domains\SecondBrain\Models\ResearchTopic;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\SecondBrain\Concerns\InteractsWithSecondBrain;
use Tests\TestCase;

final class KnowledgeItemLifecycleTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithSecondBrain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_knowledge_items_capture_sources_and_citations_with_versioned_updates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $created = $this->withHeaders($this->headers())
            ->postJson('/api/v1/knowledge', [
                'title' => '  Attention Is All You Need  ',
                'summary' => 'Introduces the transformer architecture.',
                'source_type' => 'link',
                'source_url' => 'https://arxiv.org/abs/1706.03762',
                'authors' => 'Vaswani, A. and Shazeer, N.',
                'published_year' => 2017,
                'venue' => 'NeurIPS',
                'doi' => '10.48550/arXiv.1706.03762',
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.title', 'Attention Is All You Need')
            ->assertJsonPath('data.source.type', 'link')
            ->assertJsonPath('data.source.url', 'https://arxiv.org/abs/1706.03762')
            ->assertJsonPath('data.citation.published_year', 2017)
            ->assertJsonMissingPath('data.user_id');
        $itemId = $created->json('data.id');
        $this->assertIsString($itemId);

        $updated = $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$itemId}", [
                'title' => 'Attention Is All You Need',
                'summary' => 'Transformers replace recurrence with attention.',
                'authors' => 'Vaswani et al.',
                'published_year' => 2017,
                'venue' => 'NeurIPS 2017',
                'doi' => '10.48550/arXiv.1706.03762',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.citation.venue', 'NeurIPS 2017');
        $updatedAt = $updated->json('data.updated_at');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$itemId}", [
                'title' => 'Attention Is All You Need',
                'summary' => 'Transformers replace recurrence with attention.',
                'authors' => 'Vaswani et al.',
                'published_year' => 2017,
                'venue' => 'NeurIPS 2017',
                'doi' => '10.48550/arXiv.1706.03762',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.updated_at', $updatedAt);

        $stale = $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$itemId}", [
                'title' => 'Renamed title',
                'expected_version' => 1,
            ]);
        $this->assertApiError($stale, 409, ApiErrorCode::Conflict);

        $sourceMutation = $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$itemId}", [
                'title' => 'Attention Is All You Need',
                'source_url' => 'https://evil.example/replaced',
                'expected_version' => 2,
            ]);
        $this->assertApiError($sourceMutation, 422, ApiErrorCode::ValidationFailed);
    }

    public function test_unsafe_or_inconsistent_sources_are_rejected_before_persistence(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $plainHttp = $this->withHeaders($this->headers())
            ->postJson('/api/v1/knowledge', [
                'title' => 'Insecure link',
                'source_type' => 'link',
                'source_url' => 'http://example.com/paper',
            ]);
        $this->assertApiError($plainHttp, 422, ApiErrorCode::ValidationFailed);

        $missingResource = $this->withHeaders($this->headers())
            ->postJson('/api/v1/knowledge', [
                'title' => 'Missing file',
                'source_type' => 'resource',
            ]);
        $this->assertApiError($missingResource, 422, ApiErrorCode::ValidationFailed);

        $foreignResource = Resource::factory()->link()->create();
        $foreign = $this->withHeaders($this->headers())
            ->postJson('/api/v1/knowledge', [
                'title' => 'Someone else’s file',
                'source_type' => 'resource',
                'resource_id' => (string) $foreignResource->public_id,
            ]);
        $this->assertApiError($foreign, 404, ApiErrorCode::ResourceNotFound);

        self::assertSame(0, KnowledgeItem::query()->count());
    }

    public function test_resource_backed_items_preserve_original_source_access(): void
    {
        $user = User::factory()->create();
        $resource = Resource::factory()->for($user, 'user')->link('https://example.edu/syllabus')->create();
        $this->actingAs($user, 'web');

        $created = $this->withHeaders($this->headers())
            ->postJson('/api/v1/knowledge', [
                'title' => 'Course syllabus',
                'source_type' => 'resource',
                'resource_id' => (string) $resource->public_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.source.type', 'resource')
            ->assertJsonPath('data.source.resource.id', (string) $resource->public_id);
        $itemId = $created->json('data.id');

        $blockedDelete = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/resources/{$resource->public_id}", ['expected_version' => 1]);
        $this->assertApiError($blockedDelete, 409, ApiErrorCode::Conflict);
        $this->assertDatabaseHas('resources', ['public_id' => (string) $resource->public_id]);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/knowledge/{$itemId}", ['expected_version' => 1])
            ->assertNoContent();

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/resources/{$resource->public_id}", ['expected_version' => 1])
            ->assertNoContent();
    }

    public function test_source_provenance_is_immutable_at_the_database_boundary(): void
    {
        $user = User::factory()->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->linkSource()->create();

        $this->expectExceptionMessage('knowledge_items source provenance is immutable.');
        DB::table('knowledge_items')
            ->where('id', $item->getKey())
            ->update(['source_url' => 'https://tampered.example/doc']);
    }

    public function test_deleting_an_item_cascades_annotations_links_and_memberships(): void
    {
        $user = User::factory()->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create();
        $other = KnowledgeItem::factory()->for($user, 'user')->create();
        $note = KnowledgeNote::factory()->forItem($item)->create();
        $tag = KnowledgeTag::factory()->for($user, 'user')->create();
        $topic = ResearchTopic::factory()->for($user, 'user')->create();
        DB::table('knowledge_item_tags')->insert([
            'user_id' => $user->getKey(),
            'knowledge_item_id' => $item->getKey(),
            'knowledge_tag_id' => $tag->getKey(),
        ]);
        DB::table('knowledge_links')->insert([
            'user_id' => $user->getKey(),
            'from_item_id' => $item->getKey(),
            'to_item_id' => $other->getKey(),
            'relation_type' => 'related',
        ]);
        DB::table('research_topic_sources')->insert([
            'user_id' => $user->getKey(),
            'research_topic_id' => $topic->getKey(),
            'knowledge_item_id' => $item->getKey(),
            'reading_status' => 'to_read',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->actingAs($user, 'web');
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/knowledge/{$item->public_id}", ['expected_version' => 1])
            ->assertNoContent();

        $this->assertDatabaseMissing('knowledge_notes', ['id' => $note->getKey()]);
        $this->assertDatabaseMissing('knowledge_item_tags', ['knowledge_item_id' => $item->getKey()]);
        $this->assertDatabaseMissing('knowledge_links', ['from_item_id' => $item->getKey()]);
        $this->assertDatabaseMissing('research_topic_sources', ['knowledge_item_id' => $item->getKey()]);
        $this->assertDatabaseHas('knowledge_tags', ['id' => $tag->getKey()]);
        $this->assertDatabaseHas('knowledge_items', ['id' => $other->getKey()]);
        $this->assertDatabaseHas('research_topics', ['id' => $topic->getKey()]);
    }
}
