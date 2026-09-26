<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "Saved" bookmark for Second Brain knowledge items.
 *
 * A knowledge item is directly student-owned, so "saved" is a nullable
 * timestamp on the row itself rather than a separate preference table. The
 * preference-table pattern belongs to rows the student does not own (the
 * guidance catalog); an owned row records its own curation, exactly as every
 * other owned surface already does.
 *
 * Two parts:
 *  1. knowledge_items.saved_at            NULL means not saved; a timestamp is
 *                                         when the student bookmarked the item.
 *  2. a partial cursor index scoped to the saved rows, sized for the
 *     saved-only listing.
 *
 * down() refuses to erase the bookmarks, mirroring ..._000019: a saved_at is
 * student curation, so the rollback is blocked while any is set rather than
 * dropped without a trace.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE knowledge_items ADD COLUMN saved_at TIMESTAMPTZ(0) NULL');

        DB::statement(<<<'SQL'
            CREATE INDEX knowledge_items_owner_saved_cursor_idx
            ON knowledge_items (user_id, saved_at DESC, public_id DESC)
            WHERE saved_at IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->guardSavedItems();

            if (Schema::hasTable('knowledge_items')) {
                DB::statement('DROP INDEX IF EXISTS knowledge_items_owner_saved_cursor_idx');
                DB::statement('ALTER TABLE knowledge_items DROP COLUMN IF EXISTS saved_at');
            }
        }, 3);
    }

    /**
     * A saved_at is student curation, so refuse the rollback rather than erase
     * it, exactly as ..._000019 guards private study data.
     */
    private function guardSavedItems(): void
    {
        if (Schema::hasTable('knowledge_items')
            && Schema::hasColumn('knowledge_items', 'saved_at')) {
            DB::statement('LOCK TABLE knowledge_items IN ACCESS EXCLUSIVE MODE');

            if (DB::table('knowledge_items')->whereNotNull('saved_at')->exists()) {
                throw new RuntimeException(
                    'Cannot roll back the knowledge item bookmark while saved items exist.',
                );
            }
        }
    }
};
