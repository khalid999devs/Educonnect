<?php

declare(strict_types=1);

namespace Tests\Feature\SecondBrain;

use App\Domains\SecondBrain\Models\Collection;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\SecondBrain\Concerns\InteractsWithSecondBrain;
use Tests\TestCase;

final class CollectionLifecycleTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithSecondBrain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_collection_lifecycle_is_versioned_idempotent_and_duplicate_safe(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $created = $this->withHeaders($this->headers())
            ->postJson('/api/v1/collections', [
                'name' => '  Thesis sources  ',
                'description' => 'Primary papers for the thesis.',
                'kind' => 'research',
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.name', 'Thesis sources')
            ->assertJsonPath('data.kind', 'research')
            ->assertJsonPath('data.item_count', 0)
            ->assertJsonMissingPath('data.user_id');
        $collectionId = $created->json('data.id');
        $this->assertIsString($collectionId);

        $duplicate = $this->withHeaders($this->headers())
            ->postJson('/api/v1/collections', ['name' => 'THESIS SOURCES']);
        $this->assertApiError($duplicate, 422, ApiErrorCode::ValidationFailed);

        $updated = $this->withHeaders($this->headers())
            ->putJson("/api/v1/collections/{$collectionId}", [
                'name' => 'Thesis sources',
                'description' => 'Primary and secondary papers.',
                'kind' => 'research',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);
        $updatedAt = $updated->json('data.updated_at');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/collections/{$collectionId}", [
                'name' => 'Thesis sources',
                'description' => 'Primary and secondary papers.',
                'kind' => 'research',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.updated_at', $updatedAt);

        $staleUpdate = $this->withHeaders($this->headers())
            ->putJson("/api/v1/collections/{$collectionId}", [
                'name' => 'Renamed collection',
                'description' => null,
                'kind' => 'general',
                'expected_version' => 1,
            ]);
        $this->assertApiError($staleUpdate, 409, ApiErrorCode::Conflict);

        $unknownField = $this->withHeaders($this->headers())
            ->postJson('/api/v1/collections', ['name' => 'Another', 'owner' => 'me']);
        $this->assertApiError($unknownField, 422, ApiErrorCode::ValidationFailed);

        $listed = $this->withHeaders($this->headers())
            ->getJson('/api/v1/collections?kind=research')
            ->assertOk()
            ->assertJsonPath('meta.summary.total', 1);
        self::assertCount(1, $listed->json('data'));

        $staleDelete = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/collections/{$collectionId}", ['expected_version' => 1]);
        $this->assertApiError($staleDelete, 409, ApiErrorCode::Conflict);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/collections/{$collectionId}", ['expected_version' => 2])
            ->assertNoContent();
        $this->assertDatabaseMissing('collections', ['public_id' => $collectionId]);
    }

    public function test_deleting_a_collection_detaches_items_without_deleting_them(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->for($user, 'user')->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create();
        DB::table('collection_knowledge_items')->insert([
            'user_id' => $user->getKey(),
            'collection_id' => $collection->getKey(),
            'knowledge_item_id' => $item->getKey(),
        ]);

        $this->actingAs($user, 'web');
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/collections/{$collection->public_id}", ['expected_version' => 1])
            ->assertNoContent();

        $this->assertDatabaseMissing('collection_knowledge_items', [
            'knowledge_item_id' => $item->getKey(),
        ]);
        $this->assertDatabaseHas('knowledge_items', ['public_id' => (string) $item->public_id]);
    }

    public function test_collection_names_are_searchable_and_pagination_is_cursor_based(): void
    {
        $user = User::factory()->create();
        Collection::factory()->for($user, 'user')->create(['name' => 'Algorithms deep dive']);
        Collection::factory()->for($user, 'user')->create(['name' => 'Biology field notes']);

        $this->actingAs($user, 'web');
        $found = $this->withHeaders($this->headers())
            ->getJson('/api/v1/collections?search=algo')
            ->assertOk();
        self::assertCount(1, $found->json('data'));
        self::assertSame('Algorithms deep dive', $found->json('data.0.name'));

        $paged = $this->withHeaders($this->headers())
            ->getJson('/api/v1/collections?per_page=1')
            ->assertOk();
        self::assertNotNull($paged->json('meta.pagination.next_cursor'));
    }
}
