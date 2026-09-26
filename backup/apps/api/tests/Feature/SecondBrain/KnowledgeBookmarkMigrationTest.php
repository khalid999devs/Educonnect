<?php

declare(strict_types=1);

namespace Tests\Feature\SecondBrain;

use App\Domains\SecondBrain\Models\KnowledgeItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Asserts migration `2026_07_21_000020_add_knowledge_item_bookmark` directly
 * against the live PostgreSQL schema.
 *
 * This is the newest schema-bearing migration, so nothing is stacked above it:
 * its down() can be called directly without lowering a dependent first, and no
 * RollsBackDependentMigrations dance is required.
 */
final class KnowledgeBookmarkMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_07_21_000020_add_knowledge_item_bookmark.php';

    private const INDEX = 'knowledge_items_owner_saved_cursor_idx';

    public function test_saved_at_column_and_partial_index_exist(): void
    {
        self::assertTrue(Schema::hasColumn('knowledge_items', 'saved_at'));
        self::assertTrue($this->indexExists(self::INDEX));
    }

    public function test_a_saved_item_reads_back_its_bookmark_timestamp(): void
    {
        $item = KnowledgeItem::factory()->create();

        self::assertNull(DB::table('knowledge_items')->where('id', $item->getKey())->value('saved_at'));

        DB::table('knowledge_items')->where('id', $item->getKey())->update(['saved_at' => now('UTC')]);

        self::assertNotNull(DB::table('knowledge_items')->where('id', $item->getKey())->value('saved_at'));
    }

    public function test_migration_rolls_back_when_empty_and_refuses_saved_items(): void
    {
        $migration = $this->migration();

        $migration->down();
        self::assertFalse(Schema::hasColumn('knowledge_items', 'saved_at'));
        self::assertFalse($this->indexExists(self::INDEX));

        $migration->up();
        self::assertTrue(Schema::hasColumn('knowledge_items', 'saved_at'));
        self::assertTrue($this->indexExists(self::INDEX));

        $item = KnowledgeItem::factory()->create();
        DB::table('knowledge_items')->where('id', $item->getKey())->update(['saved_at' => now('UTC')]);

        try {
            $migration->down();
            self::fail('The bookmark migration erased saved items.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('saved items exist', $exception->getMessage());
        }

        self::assertTrue(Schema::hasColumn('knowledge_items', 'saved_at'));
        $this->assertDatabaseHas('knowledge_items', ['id' => $item->getKey()]);
    }

    private function migration(): Migration
    {
        $migration = require database_path(self::MIGRATION);
        self::assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function indexExists(string $index): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_indexes WHERE indexname = ?', [$index]) !== null;
    }
}
