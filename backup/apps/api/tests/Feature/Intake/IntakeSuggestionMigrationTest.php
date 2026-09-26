<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Domains\Intake\Models\IntakeSuggestion;
use App\Domains\Planner\Models\Task;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class IntakeSuggestionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestion_identity_payload_and_status_transitions_are_database_enforced(): void
    {
        $suggestion = IntakeSuggestion::factory()->create();

        $this->assertQueryRejected('23514', static fn () => DB::table('intake_suggestions')
            ->where('id', $suggestion->getKey())
            ->update(['payload' => json_encode(['title' => 'tampered'])]));
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_suggestions')
            ->where('id', $suggestion->getKey())
            ->update(['reason' => 'rewritten reasoning']));
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_suggestions')
            ->where('id', $suggestion->getKey())
            ->update(['confidence' => '0.100']));

        // applied is terminal and cannot revert.
        DB::table('intake_suggestions')->where('id', $suggestion->getKey())->update(['status' => 'dismissed']);
        DB::table('intake_suggestions')->where('id', $suggestion->getKey())->update(['status' => 'proposed']);
        $task = Task::factory()->create();
        DB::table('intake_suggestions')->where('id', $suggestion->getKey())->update([
            'status' => 'applied',
            'created_task_id' => $task->getKey(),
        ]);
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_suggestions')
            ->where('id', $suggestion->getKey())
            ->update(['status' => 'proposed']));

        // Created targets are only legal on applied suggestions.
        $fresh = IntakeSuggestion::factory()->create();
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_suggestions')
            ->where('id', $fresh->getKey())
            ->update(['created_task_id' => $task->getKey()]));

        // Deleting the created task clears provenance instead of blocking.
        DB::table('tasks')->where('id', $task->getKey())->delete();
        self::assertNull(DB::table('intake_suggestions')->where('id', $suggestion->getKey())->value('created_task_id'));

        // Classification metadata is bounded.
        $item = $suggestion->item;
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_items')
            ->where('id', $item->getKey())
            ->update(['classification_latency_ms' => -1]));
    }

    public function test_migration_rolls_back_atomically_when_empty_and_refuses_suggestion_evidence(): void
    {
        $migration = $this->migration();

        $migration->down();
        self::assertFalse(Schema::hasTable('intake_suggestions'));
        self::assertFalse(Schema::hasColumn('intake_items', 'classification_provider'));

        $migration->up();
        self::assertTrue(Schema::hasTable('intake_suggestions'));
        self::assertTrue(Schema::hasColumn('intake_items', 'classification_provider'));

        $suggestion = IntakeSuggestion::factory()->create();

        try {
            $migration->down();
            self::fail('The suggestions migration erased private suggestion data.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('private suggestion data exists', $exception->getMessage());
        }

        self::assertTrue(Schema::hasTable('intake_suggestions'));
        $this->assertDatabaseHas('intake_suggestions', ['id' => $suggestion->getKey()]);
    }

    private function migration(): Migration
    {
        $migration = require database_path(
            'migrations/2026_07_16_000014_create_intake_suggestions.php',
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
