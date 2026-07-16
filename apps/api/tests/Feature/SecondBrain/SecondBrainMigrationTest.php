<?php

declare(strict_types=1);

namespace Tests\Feature\SecondBrain;

use App\Domains\SecondBrain\Models\Collection;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\KnowledgeNote;
use App\Domains\SecondBrain\Models\KnowledgeTag;
use App\Domains\SecondBrain\Models\ResearchTopic;
use App\Domains\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SecondBrainMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_and_shape_constraints_are_database_enforced(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->for($user, 'user')->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create();
        $note = KnowledgeNote::factory()->forItem($item)->create();
        $topic = ResearchTopic::factory()->for($user, 'user')->create();

        $this->assertQueryRejected('23514', static fn () => DB::table('collections')
            ->where('id', $collection->getKey())
            ->update(['user_id' => User::factory()->create()->getKey()]));
        $this->assertQueryRejected('23514', static fn () => DB::table('knowledge_items')
            ->where('id', $item->getKey())
            ->update(['public_id' => '01zzzzzzzzzzzzzzzzzzzzzzzz']));
        $this->assertQueryRejected('23514', static fn () => DB::table('knowledge_notes')
            ->where('id', $note->getKey())
            ->update(['knowledge_item_id' => KnowledgeItem::factory()->for($note->user, 'user')->create()->getKey()]));

        $this->assertQueryRejected('23514', static fn () => DB::table('collections')->insert([
            'user_id' => $collection->user_id,
            'name' => 'Bad kind',
            'kind' => 'secret',
            'version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]));
        $this->assertQueryRejected('23514', static fn () => DB::table('knowledge_items')->insert([
            'user_id' => $item->user_id,
            'source_type' => 'link',
            'source_url' => 'http://insecure.example/doc',
            'title' => 'Insecure',
            'version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]));
        $this->assertQueryRejected('23514', static fn () => DB::table('research_topics')
            ->where('id', $topic->getKey())
            ->update(['keywords' => json_encode(['nested' => 'object'])]));
        $this->assertQueryRejected('23514', static fn () => DB::table('research_topic_sources')->insert([
            'user_id' => $user->getKey(),
            'research_topic_id' => $topic->getKey(),
            'knowledge_item_id' => $item->getKey(),
            'reading_status' => 'skimmed',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]));

        // Same-owner composite keys refuse cross-user annotations.
        $stranger = User::factory()->create();
        $this->assertQueryRejected('23503', static fn () => DB::table('knowledge_notes')->insert([
            'user_id' => $stranger->getKey(),
            'knowledge_item_id' => $item->getKey(),
            'body' => 'cross-user note',
            'version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]));

        // Duplicate memberships and self-links are structurally impossible.
        $tag = KnowledgeTag::factory()->for($user, 'user')->create();
        DB::table('knowledge_item_tags')->insert([
            'user_id' => $user->getKey(),
            'knowledge_item_id' => $item->getKey(),
            'knowledge_tag_id' => $tag->getKey(),
        ]);
        $this->assertQueryRejected('23505', static fn () => DB::table('knowledge_item_tags')->insert([
            'user_id' => $user->getKey(),
            'knowledge_item_id' => $item->getKey(),
            'knowledge_tag_id' => $tag->getKey(),
        ]));
        $this->assertQueryRejected('23514', static fn () => DB::table('knowledge_links')->insert([
            'user_id' => $user->getKey(),
            'from_item_id' => $item->getKey(),
            'to_item_id' => $item->getKey(),
            'relation_type' => 'related',
        ]));
    }

    public function test_user_deletion_cascades_the_whole_second_brain(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->for($user, 'user')->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create();
        $other = KnowledgeItem::factory()->for($user, 'user')->create();
        KnowledgeNote::factory()->forItem($item)->create();
        $tag = KnowledgeTag::factory()->for($user, 'user')->create();
        $topic = ResearchTopic::factory()->for($user, 'user')->create();
        DB::table('knowledge_item_tags')->insert([
            'user_id' => $user->getKey(),
            'knowledge_item_id' => $item->getKey(),
            'knowledge_tag_id' => $tag->getKey(),
        ]);
        DB::table('collection_knowledge_items')->insert([
            'user_id' => $user->getKey(),
            'collection_id' => $collection->getKey(),
            'knowledge_item_id' => $item->getKey(),
        ]);
        DB::table('knowledge_links')->insert([
            'user_id' => $user->getKey(),
            'from_item_id' => $item->getKey(),
            'to_item_id' => $other->getKey(),
            'relation_type' => 'supports',
        ]);
        DB::table('research_topic_sources')->insert([
            'user_id' => $user->getKey(),
            'research_topic_id' => $topic->getKey(),
            'knowledge_item_id' => $item->getKey(),
            'reading_status' => 'to_read',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('users')->where('id', $user->getKey())->delete();

        foreach ([
            'collections', 'knowledge_items', 'knowledge_notes', 'knowledge_tags',
            'knowledge_item_tags', 'collection_knowledge_items', 'knowledge_links',
            'research_topics', 'research_topic_sources',
        ] as $table) {
            self::assertSame(0, DB::table($table)->count(), "Expected {$table} to cascade with the user.");
        }
    }

    public function test_migration_rolls_back_atomically_when_empty_and_refuses_knowledge_evidence(): void
    {
        $migration = $this->migration();

        $migration->down();
        self::assertFalse(Schema::hasTable('knowledge_items'));
        self::assertFalse(Schema::hasTable('collections'));
        self::assertFalse(Schema::hasTable('research_topics'));

        $migration->up();
        self::assertTrue(Schema::hasTable('knowledge_items'));

        $item = KnowledgeItem::factory()->create();

        try {
            $migration->down();
            self::fail('The Second Brain migration erased private knowledge data.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('knowledge or research data exists', $exception->getMessage());
        }

        self::assertTrue(Schema::hasTable('knowledge_items'));
        $this->assertDatabaseHas('knowledge_items', ['id' => $item->getKey()]);
    }

    private function migration(): Migration
    {
        $migration = require database_path(
            'migrations/2026_07_16_000015_create_second_brain_foundation.php',
        );
        self::assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    /** @param callable(): mixed $operation */
    private function assertQueryRejected(string $expectedSqlState, callable $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            self::assertSame($expectedSqlState, $exception->errorInfo[0] ?? null);

            return;
        }

        self::fail("Expected PostgreSQL to reject the statement with SQLSTATE {$expectedSqlState}.");
    }
}
