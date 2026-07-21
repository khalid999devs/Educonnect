<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rollback tests that `require` a single migration file and call `down()` on it
 * bypass the migration chain, so nothing lowers the migrations stacked above it
 * first. `php artisan migrate:rollback` never has that problem: it walks the
 * batch newest-first, so every dependent migration is already down by the time
 * an older foundation is asked to drop its tables.
 *
 * `..._000019_create_study_and_purpose_foundation` is the newest schema-bearing
 * migration, and it hangs foreign keys off tables that older foundations own:
 *
 *   intake_suggestions_knowledge_item_foreign -> knowledge_items  (..._000015)
 *   study_artifacts_owner_item_foreign        -> knowledge_items  (..._000015)
 *   knowledge_items_owner_intake_item_foreign -> intake_items     (..._000013)
 *
 * PostgreSQL therefore refuses `DROP TABLE knowledge_items` / `intake_items`
 * with SQLSTATE 2BP01 while ..._000019 is still applied. That refusal is correct
 * database behaviour: it is the tests that were skipping a step, not the schema
 * that is wrong. Nothing here weakens a drop (no CASCADE), and no older
 * migration is made aware of a newer one.
 *
 * Call rollBackStudyAndPurposeFoundation() before lowering an older foundation
 * and restoreStudyAndPurposeFoundation() after raising it again, so the test
 * reproduces the production rollback and re-migrate ordering exactly and leaves
 * the database consistent for the remaining assertions and for RefreshDatabase.
 *
 * Both methods are idempotent: they inspect the live schema and do nothing when
 * the database already holds the requested state.
 */
trait RollsBackDependentMigrations
{
    /** Lower ..._000019 if it is applied, mirroring `migrate:rollback`. */
    protected function rollBackStudyAndPurposeFoundation(): void
    {
        if (! $this->studyAndPurposeFoundationIsApplied()) {
            return;
        }

        $this->studyAndPurposeFoundation()->down();
    }

    /**
     * Raise ..._000019 again if it is down, mirroring `migrate`. The foundations
     * that own the tables it extends must already be back up, exactly as the
     * chain guarantees in production.
     */
    protected function restoreStudyAndPurposeFoundation(): void
    {
        if ($this->studyAndPurposeFoundationIsApplied()) {
            return;
        }

        foreach (['intake_items', 'intake_suggestions', 'knowledge_items', 'stored_files'] as $table) {
            self::assertTrue(
                Schema::hasTable($table),
                "The study and purpose foundation extends {$table}, so the migration that owns it must be raised first.",
            );
        }

        $this->studyAndPurposeFoundation()->up();
    }

    /**
     * `study_artifacts` is created by ..._000019's up() and dropped by its
     * down(), both inside that migration's own transaction, so its presence is
     * an exact record of whether the migration is currently applied.
     */
    private function studyAndPurposeFoundationIsApplied(): bool
    {
        return Schema::hasTable('study_artifacts');
    }

    private function studyAndPurposeFoundation(): Migration
    {
        $migration = require database_path(
            'migrations/2026_07_20_000019_create_study_and_purpose_foundation.php',
        );
        self::assertInstanceOf(Migration::class, $migration);

        return $migration;
    }
}
