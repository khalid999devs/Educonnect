<?php

declare(strict_types=1);

namespace Tests\Feature\Planner;

use App\Domains\Courses\Models\Course;
use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\RollsBackDependentMigrations;
use Tests\TestCase;

final class PlannerMigrationTest extends TestCase
{
    use RefreshDatabase;
    use RollsBackDependentMigrations;

    public function test_public_identity_ownership_and_same_owner_targets_are_database_enforced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $owner->getKey()]);
        $foreignCourse = Course::factory()->create(['user_id' => $other->getKey()]);
        $task = Task::factory()->forCourse($course)->create();
        $foreignTask = Task::factory()->forCourse($foreignCourse)->create();
        $focusSession = FocusSession::factory()->forTask($task)->create();
        $rawTaskId = DB::table('tasks')->insertGetId([
            'user_id' => $owner->getKey(),
            'course_id' => null,
            'title' => 'Database-generated task identity',
            'description' => null,
            'due_at' => null,
            'completed_at' => null,
            'archived_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rawFocusSessionId = DB::table('focus_sessions')->insertGetId([
            'user_id' => $owner->getKey(),
            'task_id' => null,
            'course_id' => null,
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
            'note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $databaseTaskPublicId = DB::table('tasks')->where('id', $rawTaskId)->value('public_id');
        $databaseFocusPublicId = DB::table('focus_sessions')
            ->where('id', $rawFocusSessionId)
            ->value('public_id');

        foreach ([$task->public_id, $focusSession->public_id, $databaseTaskPublicId, $databaseFocusPublicId] as $publicId) {
            $this->assertIsString($publicId);
            $this->assertTrue(Str::isUlid($publicId));
            $this->assertSame(strtolower($publicId), $publicId);
            $this->assertMatchesRegularExpression(
                '/^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$/',
                $publicId,
            );
        }

        $this->assertSame('public_id', $task->getRouteKeyName());
        $this->assertSame('public_id', $focusSession->getRouteKeyName());

        $this->assertQueryRejected('23514', fn () => DB::table('tasks')
            ->where('id', $task->getKey())
            ->update(['public_id' => strtolower((string) Str::ulid())]));
        $this->assertQueryRejected('23514', fn () => DB::table('tasks')
            ->where('id', $task->getKey())
            ->update(['user_id' => $other->getKey()]));
        $this->assertQueryRejected('23514', fn () => DB::table('focus_sessions')
            ->where('id', $focusSession->getKey())
            ->update(['public_id' => strtolower((string) Str::ulid())]));
        $this->assertQueryRejected('23514', fn () => DB::table('focus_sessions')
            ->where('id', $focusSession->getKey())
            ->update(['user_id' => $other->getKey()]));
        $this->assertQueryRejected('23503', fn () => DB::table('tasks')
            ->where('id', $task->getKey())
            ->update(['course_id' => $foreignCourse->getKey()]));
        $this->assertQueryRejected('23503', fn () => DB::table('focus_sessions')
            ->where('id', $focusSession->getKey())
            ->update(['task_id' => $foreignTask->getKey()]));
        $this->assertQueryRejected('23503', fn () => DB::table('focus_sessions')
            ->where('id', $focusSession->getKey())
            ->update(['task_id' => null, 'course_id' => $foreignCourse->getKey()]));
    }

    public function test_task_status_text_version_and_completion_constraints_are_database_enforced(): void
    {
        $task = Task::factory()->create();
        $completed = Task::factory()->completed()->create();

        $this->assertQueryRejected('23514', fn () => DB::table('tasks')
            ->where('id', $task->getKey())
            ->update(['title' => '   ']));
        $this->assertQueryRejected('23514', fn () => DB::table('tasks')
            ->where('id', $task->getKey())
            ->update(['description' => '   ']));
        $this->assertQueryRejected('23514', fn () => DB::table('tasks')
            ->where('id', $task->getKey())
            ->update(['status' => 'unknown']));
        $this->assertQueryRejected('23514', fn () => DB::table('tasks')
            ->where('id', $task->getKey())
            ->update(['status' => TaskStatus::Completed->value]));
        $this->assertQueryRejected('23514', fn () => DB::table('tasks')
            ->where('id', $completed->getKey())
            ->update(['status' => TaskStatus::Pending->value]));
        $this->assertQueryRejected('23514', fn () => DB::table('tasks')
            ->where('id', $task->getKey())
            ->update(['version' => 0]));
    }

    public function test_focus_target_note_version_and_bounded_time_range_are_database_enforced(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->getKey()]);
        $task = Task::factory()->forCourse($course)->create();
        $focusSession = FocusSession::factory()->create(['user_id' => $user->getKey()]);
        $startsAt = CarbonImmutable::parse('2026-07-14T09:00:00Z');

        $this->assertQueryRejected('23514', fn () => DB::table('focus_sessions')
            ->where('id', $focusSession->getKey())
            ->update(['task_id' => $task->getKey(), 'course_id' => $course->getKey()]));
        $this->assertQueryRejected('23514', fn () => DB::table('focus_sessions')
            ->where('id', $focusSession->getKey())
            ->update(['starts_at' => $startsAt, 'ends_at' => $startsAt]));
        $this->assertQueryRejected('23514', fn () => DB::table('focus_sessions')
            ->where('id', $focusSession->getKey())
            ->update([
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addDay()->addSecond(),
            ]));
        $this->assertQueryRejected('23514', fn () => DB::table('focus_sessions')
            ->where('id', $focusSession->getKey())
            ->update(['note' => '   ']));
        $this->assertQueryRejected('23514', fn () => DB::table('focus_sessions')
            ->where('id', $focusSession->getKey())
            ->update(['version' => 0]));

        DB::table('focus_sessions')->where('id', $focusSession->getKey())->update([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addDay(),
        ]);
        $this->assertDatabaseHas('focus_sessions', ['id' => $focusSession->getKey()]);
    }

    public function test_target_deletion_is_blocked_while_user_deletion_cascades_the_owned_aggregate(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->getKey()]);
        $task = Task::factory()->forCourse($course)->create();
        $taskFocus = FocusSession::factory()->forTask($task)->create();
        $courseFocus = FocusSession::factory()->forCourse($course)->create();

        $this->assertQueryRejected('23503', fn () => DB::table('courses')
            ->where('id', $course->getKey())
            ->delete());
        $this->assertQueryRejected('23503', fn () => DB::table('tasks')
            ->where('id', $task->getKey())
            ->delete());

        $this->assertDatabaseHas('tasks', ['id' => $task->getKey(), 'course_id' => $course->getKey()]);
        $this->assertDatabaseHas('focus_sessions', [
            'id' => $taskFocus->getKey(),
            'task_id' => $task->getKey(),
        ]);
        $this->assertDatabaseHas('focus_sessions', [
            'id' => $courseFocus->getKey(),
            'course_id' => $course->getKey(),
        ]);

        $user->deleteOrFail();
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('focus_sessions', 0);
    }

    public function test_planner_datetimes_and_access_path_indexes_match_the_postgresql_contract(): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('table_name', 'tasks')
                        ->whereIn('column_name', [
                            'due_at',
                            'completed_at',
                            'archived_at',
                            'created_at',
                            'updated_at',
                        ]);
                })->orWhere(function ($query): void {
                    $query->where('table_name', 'focus_sessions')
                        ->whereIn('column_name', ['starts_at', 'ends_at', 'created_at', 'updated_at']);
                });
            })
            ->get(['table_name', 'column_name', 'data_type', 'datetime_precision']);

        $this->assertCount(9, $columns);

        foreach ($columns as $column) {
            $this->assertSame('timestamp with time zone', $column->data_type);
            $this->assertSame(0, $column->datetime_precision);
        }

        $indexes = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->whereIn('indexname', [
                'tasks_owner_active_due_cursor_idx',
                'tasks_owner_active_status_due_idx',
                'tasks_owner_course_lookup_idx',
                'tasks_owner_title_prefix_idx',
                'focus_sessions_owner_starts_cursor_idx',
                'focus_sessions_owner_time_range_idx',
                'focus_sessions_owner_task_lookup_idx',
                'focus_sessions_owner_course_lookup_idx',
            ])
            ->pluck('indexdef', 'indexname');

        $this->assertCount(8, $indexes);
        $this->assertStringContainsString(
            '(user_id, due_at, public_id)',
            (string) $indexes['tasks_owner_active_due_cursor_idx'],
        );
        $this->assertStringContainsString(
            'lower((title)::text) text_pattern_ops',
            strtolower((string) $indexes['tasks_owner_title_prefix_idx']),
        );
        $this->assertStringContainsString(
            '(user_id, starts_at, public_id)',
            (string) $indexes['focus_sessions_owner_starts_cursor_idx'],
        );
        $this->assertStringContainsString(
            '(user_id, starts_at, ends_at)',
            (string) $indexes['focus_sessions_owner_time_range_idx'],
        );
        $this->assertStringContainsString(
            '(user_id, task_id, starts_at, public_id)',
            (string) $indexes['focus_sessions_owner_task_lookup_idx'],
        );
        $this->assertStringContainsString(
            '(user_id, course_id, starts_at, public_id)',
            (string) $indexes['focus_sessions_owner_course_lookup_idx'],
        );
    }

    public function test_migration_rolls_back_when_empty_and_refuses_private_planner_rows(): void
    {
        $migration = $this->migration();
        $resourceMigration = $this->resourceMigration();
        $templateMigration = $this->templateMigration();
        $intakeMigration = $this->intakeMigration();
        $suggestionMigration = $this->suggestionMigration();
        $secondBrainMigration = $this->secondBrainMigration();
        $communityMigration = $this->communityMigration();
        $statements = [];
        DB::listen(static function (QueryExecuted $query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        // Phase 29 study artifacts and knowledge purposes hang off
        // knowledge_items(user_id, id) and intake_items(user_id, id), so they
        // lower before the foundations that own those tables.
        $this->rollBackStudyAndPurposeFoundation();
        $communityMigration->down();
        $secondBrainMigration->down();
        $suggestionMigration->down();
        $intakeMigration->down();
        $templateMigration->down();
        $resourceMigration->down();
        $migration->down();
        $this->assertContains('LOCK TABLE tasks IN ACCESS EXCLUSIVE MODE', $statements);
        $this->assertContains('LOCK TABLE focus_sessions IN ACCESS EXCLUSIVE MODE', $statements);
        $this->assertFalse(Schema::hasTable('focus_sessions'));
        $this->assertFalse(Schema::hasTable('tasks'));
        $this->assertFalse($this->constraintExists('courses_owner_id_unique'));

        $migration->up();
        $resourceMigration->up();
        $templateMigration->up();
        $intakeMigration->up();
        $suggestionMigration->up();
        $secondBrainMigration->up();
        $communityMigration->up();
        $this->restoreStudyAndPurposeFoundation();
        $this->assertTrue(Schema::hasTable('tasks'));
        $this->assertTrue(Schema::hasTable('focus_sessions'));
        $this->assertTrue($this->constraintExists('courses_owner_id_unique'));

        $task = Task::factory()->create();

        try {
            $migration->down();
            self::fail('The planner migration erased private task data.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('task or focus-session data exists', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('tasks'));
        $this->assertDatabaseHas('tasks', ['id' => $task->getKey()]);
    }

    private function migration(): Migration
    {
        $migration = require database_path('migrations/2026_07_14_000008_create_planner_foundation.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function resourceMigration(): Migration
    {
        $migration = require database_path('migrations/2026_07_14_000009_create_resource_storage_foundation.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function secondBrainMigration(): Migration
    {
        // Phase 16 knowledge items reference resources(user_id, id).
        $migration = require database_path('migrations/2026_07_16_000015_create_second_brain_foundation.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function communityMigration(): Migration
    {
        // Phase 25 community posts reference resources(user_id, id).
        $migration = require database_path('migrations/2026_07_18_000016_create_community_and_mentor_foundation.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function suggestionMigration(): Migration
    {
        // Phase 15 suggestions reference intake items, tasks, and resources.
        $migration = require database_path('migrations/2026_07_16_000014_create_intake_suggestions.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function intakeMigration(): Migration
    {
        // Phase 14 intake items reference resources(user_id, id).
        $migration = require database_path('migrations/2026_07_16_000013_create_intake_foundation.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function templateMigration(): Migration
    {
        // Phase 13 template copies hold a composite foreign key that depends
        // on the courses_owner_id_unique constraint this migration manages.
        $migration = require database_path('migrations/2026_07_15_000012_create_templates_and_editable_copies.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function constraintExists(string $constraint): bool
    {
        return DB::table('pg_constraint')->where('conname', $constraint)->exists();
    }

    /** @param  callable(): mixed  $operation */
    private function assertQueryRejected(string $expectedSqlState, callable $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            $this->assertSame($expectedSqlState, $exception->errorInfo[0] ?? null);

            return;
        }

        self::fail("Expected PostgreSQL to reject the statement with SQLSTATE {$expectedSqlState}.");
    }
}
