<?php

declare(strict_types=1);

namespace Tests\Feature\Courses;

use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Models\Course;
use App\Domains\Onboarding\Actions\CompleteOnboardingAction;
use App\Domains\Onboarding\Actions\UpdateOnboardingStepAction;
use App\Domains\Onboarding\Enums\OnboardingStep;
use App\Domains\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CourseMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_records_receive_public_ulids_and_database_constraints_protect_identity_and_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $term = AcademicTerm::factory()->withDates()->create(['user_id' => $owner->getKey()]);
        $course = Course::factory()->forAcademicTerm($term)->fromOnboardingPosition(0)->create();
        $otherTerm = AcademicTerm::factory()->create(['user_id' => $other->getKey()]);

        foreach ([$term->public_id, $course->public_id] as $publicId) {
            $this->assertIsString($publicId);
            $this->assertTrue(Str::isUlid($publicId));
            $this->assertSame(strtolower($publicId), $publicId);
            $this->assertMatchesRegularExpression(
                '/^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$/',
                $publicId,
            );
        }

        $this->assertSame('public_id', $term->getRouteKeyName());
        $this->assertSame('public_id', $course->getRouteKeyName());

        $this->assertQueryRejected('23514', fn () => DB::table('academic_terms')
            ->where('id', $term->getKey())
            ->update(['public_id' => strtolower((string) Str::ulid())]));
        $this->assertQueryRejected('23514', fn () => DB::table('academic_terms')
            ->where('id', $term->getKey())
            ->update(['user_id' => $other->getKey()]));
        $this->assertQueryRejected('23514', fn () => DB::table('courses')
            ->where('id', $course->getKey())
            ->update(['public_id' => strtolower((string) Str::ulid())]));
        $this->assertQueryRejected('23514', fn () => DB::table('courses')
            ->where('id', $course->getKey())
            ->update(['user_id' => $other->getKey()]));
        $this->assertQueryRejected('23514', fn () => DB::table('courses')
            ->where('id', $course->getKey())
            ->update(['onboarding_position' => 1]));
        $this->assertQueryRejected('23503', fn () => DB::table('courses')
            ->where('id', $course->getKey())
            ->update(['academic_term_id' => $otherTerm->getKey()]));
    }

    public function test_database_rejects_invalid_dates_versions_text_provenance_and_referenced_term_deletion(): void
    {
        $user = User::factory()->create();
        $term = AcademicTerm::factory()->create(['user_id' => $user->getKey()]);
        $course = Course::factory()->forAcademicTerm($term)->fromOnboardingPosition(0)->create();

        $this->assertQueryRejected('23514', fn () => DB::table('academic_terms')
            ->where('id', $term->getKey())
            ->update(['starts_on' => '2027-01-02', 'ends_on' => '2027-01-01']));
        $this->assertQueryRejected('23514', fn () => DB::table('academic_terms')
            ->where('id', $term->getKey())
            ->update(['version' => 0]));
        $this->assertQueryRejected('23514', fn () => DB::table('courses')
            ->where('id', $course->getKey())
            ->update(['title' => '   ']));
        $this->assertQueryRejected('23514', fn () => DB::table('courses')
            ->where('id', $course->getKey())
            ->update(['version' => 0]));
        $this->assertQueryRejected('23505', fn () => Course::factory()
            ->create([
                'user_id' => $user->getKey(),
                'onboarding_position' => 0,
            ]));
        $this->assertQueryRejected('23503', fn () => DB::table('academic_terms')
            ->where('id', $term->getKey())
            ->delete());

        $relationshipIndex = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('tablename', 'courses')
            ->where('indexname', 'courses_term_archive_lookup_idx')
            ->value('indexdef');
        $this->assertIsString($relationshipIndex);
        $this->assertStringContainsString('(academic_term_id, archived_at)', $relationshipIndex);

        $course->deleteOrFail();
        $term->deleteOrFail();
        $this->assertDatabaseMissing('courses', ['id' => $course->getKey()]);
        $this->assertDatabaseMissing('academic_terms', ['id' => $term->getKey()]);
    }

    public function test_user_deletion_cascades_owned_academic_data_and_all_academic_datetimes_are_timezone_aware(): void
    {
        $user = User::factory()->create();
        $term = AcademicTerm::factory()->create(['user_id' => $user->getKey()]);
        Course::factory()->forAcademicTerm($term)->archived()->create();

        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('table_name', 'academic_terms')
                        ->whereIn('column_name', ['created_at', 'updated_at']);
                })->orWhere(function ($query): void {
                    $query->where('table_name', 'courses')
                        ->whereIn('column_name', ['archived_at', 'created_at', 'updated_at']);
                })->orWhere(function ($query): void {
                    $query->where('table_name', 'onboarding_progress')
                        ->where('column_name', 'academic_materialized_at');
                });
            })
            ->get(['table_name', 'column_name', 'data_type', 'datetime_precision']);

        $this->assertCount(6, $columns);

        foreach ($columns as $column) {
            $this->assertSame('timestamp with time zone', $column->data_type);
            $this->assertSame(0, $column->datetime_precision);
        }

        $user->deleteOrFail();

        $this->assertDatabaseCount('academic_terms', 0);
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_migration_rolls_back_when_empty_and_refuses_rows_or_materialization_evidence(): void
    {
        $migration = $this->migration();
        $plannerMigration = $this->plannerMigration();

        $plannerMigration->down();
        $migration->down();
        $this->assertFalse(Schema::hasTable('courses'));
        $this->assertFalse(Schema::hasTable('academic_terms'));
        $this->assertFalse(Schema::hasColumn('onboarding_progress', 'academic_materialized_at'));

        $migration->up();
        $plannerMigration->up();
        $this->assertTrue(Schema::hasTable('courses'));
        $this->assertTrue(Schema::hasTable('academic_terms'));
        $this->assertTrue(Schema::hasColumn('onboarding_progress', 'academic_materialized_at'));

        $user = User::factory()->create();
        AcademicTerm::factory()->create(['user_id' => $user->getKey()]);

        try {
            $migration->down();
            self::fail('The migration erased academic data.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('course, term, or materialization data exists', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('academic_terms'));
        $this->assertDatabaseCount('academic_terms', 1);
    }

    public function test_migration_refuses_to_remove_a_materialization_marker_even_without_course_or_term_rows(): void
    {
        $user = User::factory()->create();
        $updates = $this->app->make(UpdateOnboardingStepAction::class);
        $version = 0;
        $version = $updates->execute($user, OnboardingStep::Institution, [
            'skip' => false,
            'data' => ['institution_name' => 'KUET', 'institution_country_code' => 'BD'],
        ], $version)->version;

        foreach ([OnboardingStep::Program, OnboardingStep::StudyStage, OnboardingStep::Courses] as $step) {
            $version = $updates->execute($user, $step, ['skip' => true, 'data' => []], $version)->version;
        }

        $version = $updates->execute($user, OnboardingStep::Goals, [
            'skip' => false,
            'data' => ['goals' => ['Plan coursework'], 'problems' => []],
        ], $version)->version;
        $version = $updates->execute(
            $user,
            OnboardingStep::FirstSource,
            ['skip' => true, 'data' => []],
            $version,
        )->version;
        $this->app->make(CompleteOnboardingAction::class)->execute($user, $version);

        $this->assertDatabaseCount('courses', 0);
        $this->assertDatabaseCount('academic_terms', 0);
        $this->assertDatabaseHas('onboarding_progress', ['user_id' => $user->getKey()]);
        $this->assertNotNull(
            DB::table('onboarding_progress')->where('user_id', $user->getKey())->value('academic_materialized_at'),
        );

        try {
            $this->migration()->down();
            self::fail('The migration erased materialization evidence.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('materialization data exists', $exception->getMessage());
        }
    }

    private function migration(): Migration
    {
        $migration = require database_path('migrations/2026_07_14_000007_create_courses_and_academic_terms.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function plannerMigration(): Migration
    {
        $migration = require database_path('migrations/2026_07_14_000008_create_planner_foundation.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
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
