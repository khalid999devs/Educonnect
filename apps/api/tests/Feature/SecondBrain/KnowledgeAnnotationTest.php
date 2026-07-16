<?php

declare(strict_types=1);

namespace Tests\Feature\SecondBrain;

use App\Domains\SecondBrain\Models\Collection;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\KnowledgeTag;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\SecondBrain\Concerns\InteractsWithSecondBrain;
use Tests\TestCase;

final class KnowledgeAnnotationTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithSecondBrain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_notes_support_versioned_annotation_on_owned_items(): void
    {
        $user = User::factory()->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create();
        $this->actingAs($user, 'web');

        $created = $this->withHeaders($this->headers())
            ->postJson("/api/v1/knowledge/{$item->public_id}/notes", [
                'body' => '  Key insight: attention scales quadratically.  ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.body', 'Key insight: attention scales quadratically.');
        $noteId = $created->json('data.id');
        $this->assertIsString($noteId);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}/notes/{$noteId}", [
                'body' => 'Attention scales quadratically with sequence length.',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $stale = $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}/notes/{$noteId}", [
                'body' => 'Different content entirely.',
                'expected_version' => 1,
            ]);
        $this->assertApiError($stale, 409, ApiErrorCode::Conflict);

        $detail = $this->withHeaders($this->headers())
            ->getJson("/api/v1/knowledge/{$item->public_id}")
            ->assertOk();
        self::assertCount(1, $detail->json('data.notes'));

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/knowledge/{$item->public_id}/notes/{$noteId}", ['expected_version' => 2])
            ->assertNoContent();
        $this->assertDatabaseMissing('knowledge_notes', ['public_id' => $noteId]);
    }

    public function test_tag_sync_creates_reuses_and_detaches_tags(): void
    {
        $user = User::factory()->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create();
        KnowledgeTag::factory()->for($user, 'user')->create(['name' => 'transformers']);
        $this->actingAs($user, 'web');

        $synced = $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}/tags", [
                'tags' => ['Transformers', 'attention', 'ATTENTION', 'nlp'],
            ])
            ->assertOk();
        self::assertSame(['attention', 'nlp', 'transformers'], $synced->json('data.tags'));
        self::assertSame(3, KnowledgeTag::query()->where('user_id', $user->getKey())->count());

        $reduced = $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}/tags", ['tags' => ['nlp']])
            ->assertOk();
        self::assertSame(['nlp'], $reduced->json('data.tags'));
        self::assertSame(3, KnowledgeTag::query()->where('user_id', $user->getKey())->count());

        $cleared = $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}/tags", ['tags' => []])
            ->assertOk();
        self::assertSame([], $cleared->json('data.tags'));
    }

    public function test_collection_sync_only_accepts_owned_collections(): void
    {
        $user = User::factory()->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create();
        $mine = Collection::factory()->for($user, 'user')->create(['name' => 'Mine']);
        $foreign = Collection::factory()->create(['name' => 'Foreign']);
        $this->actingAs($user, 'web');

        $synced = $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}/collections", [
                'collection_ids' => [(string) $mine->public_id],
            ])
            ->assertOk();
        self::assertSame((string) $mine->public_id, $synced->json('data.collections.0.id'));

        $denied = $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}/collections", [
                'collection_ids' => [(string) $foreign->public_id],
            ]);
        $this->assertApiError($denied, 404, ApiErrorCode::ResourceNotFound);
        $this->assertDatabaseHas('collection_knowledge_items', [
            'knowledge_item_id' => $item->getKey(),
            'collection_id' => $mine->getKey(),
        ]);
    }

    public function test_item_links_connect_owned_items_and_reject_self_and_duplicates(): void
    {
        $user = User::factory()->create();
        $from = KnowledgeItem::factory()->for($user, 'user')->create(['title' => 'Survey paper']);
        $to = KnowledgeItem::factory()->for($user, 'user')->create(['title' => 'Original paper']);
        $foreign = KnowledgeItem::factory()->create();
        $this->actingAs($user, 'web');

        $created = $this->withHeaders($this->headers())
            ->postJson("/api/v1/knowledge/{$from->public_id}/links", [
                'target_id' => (string) $to->public_id,
                'relation_type' => 'builds_on',
            ])
            ->assertCreated()
            ->assertJsonPath('data.relation_type', 'builds_on')
            ->assertJsonPath('data.item.id', (string) $to->public_id);
        $linkId = $created->json('data.id');
        $this->assertIsString($linkId);

        $selfLink = $this->withHeaders($this->headers())
            ->postJson("/api/v1/knowledge/{$from->public_id}/links", [
                'target_id' => (string) $from->public_id,
            ]);
        $this->assertApiError($selfLink, 422, ApiErrorCode::ValidationFailed);

        $duplicate = $this->withHeaders($this->headers())
            ->postJson("/api/v1/knowledge/{$from->public_id}/links", [
                'target_id' => (string) $to->public_id,
                'relation_type' => 'related',
            ]);
        $this->assertApiError($duplicate, 409, ApiErrorCode::Conflict);

        $foreignTarget = $this->withHeaders($this->headers())
            ->postJson("/api/v1/knowledge/{$from->public_id}/links", [
                'target_id' => (string) $foreign->public_id,
            ]);
        $this->assertApiError($foreignTarget, 404, ApiErrorCode::ResourceNotFound);

        $detail = $this->withHeaders($this->headers())
            ->getJson("/api/v1/knowledge/{$to->public_id}")
            ->assertOk();
        self::assertSame('incoming', $detail->json('data.links.0.direction'));
        self::assertSame((string) $from->public_id, $detail->json('data.links.0.item.id'));

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/knowledge/{$from->public_id}/links/{$linkId}")
            ->assertNoContent();
        $this->assertDatabaseMissing('knowledge_links', ['public_id' => $linkId]);
    }
}
