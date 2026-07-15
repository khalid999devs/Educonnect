<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Authorization\Support\AuthorizationCatalog;
use App\Domains\Onboarding\Actions\UpdateOnboardingStepAction;
use App\Domains\Onboarding\Enums\OnboardingStep;
use App\Domains\Onboarding\Exceptions\OnboardingPersistenceFailure;
use App\Domains\Onboarding\Models\OnboardingProgress;
use App\Domains\Onboarding\Queries\GetOnboardingSnapshot;
use App\Domains\Users\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class OnboardingDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderator_academic_grant_is_forward_migrated_and_catalog_aligned(): void
    {
        $migration = $this->migration('2026_07_14_000005_align_moderator_academic_capability.php');
        $moderatorRoleId = DB::table('roles')->where('key', RoleKey::Moderator->value)->value('id');
        $academicCapabilityId = DB::table('capabilities')
            ->where('key', CapabilityKey::AcademicManageOwn->value)
            ->value('id');

        $this->assertIsInt($moderatorRoleId);
        $this->assertIsInt($academicCapabilityId);
        $this->assertContains(
            CapabilityKey::AcademicManageOwn,
            AuthorizationCatalog::roleCapabilities()[RoleKey::Moderator->value],
        );
        $this->assertDatabaseHas('role_capability', [
            'role_id' => $moderatorRoleId,
            'capability_id' => $academicCapabilityId,
        ]);

        $migration->down();
        $this->assertDatabaseHas('role_capability', [
            'role_id' => $moderatorRoleId,
            'capability_id' => $academicCapabilityId,
        ]);
        $migration->up();

        $moderator = User::factory()->withRole(RoleKey::Moderator)->create();
        $this->assertTrue($moderator->hasCapability(CapabilityKey::AcademicManageOwn));
    }

    public function test_onboarding_policy_requires_capability_and_exact_ownership_without_privileged_bypass(): void
    {
        $owner = User::factory()->create();
        $otherStudent = User::factory()->create();
        $administrator = User::factory()->withRole(RoleKey::Admin)->create();
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create();

        $this->app->make(UpdateOnboardingStepAction::class)->execute(
            user: $owner,
            step: OnboardingStep::Institution,
            payload: [
                'skip' => false,
                'data' => [
                    'institution_name' => 'KUET',
                    'institution_country_code' => 'BD',
                ],
            ],
            expectedVersion: 0,
        );
        $progress = OnboardingProgress::query()->findOrFail($owner->getKey());

        $this->assertTrue(Gate::forUser($owner)->allows('view', $progress));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $progress));
        $this->assertFalse(Gate::forUser($administrator)->allows('view', $progress));
        $this->assertFalse(Gate::forUser($moderator)->allows('view', $progress));
    }

    public function test_database_constraints_and_user_deletion_protect_the_private_aggregate(): void
    {
        $user = User::factory()->create();

        $this->assertConstraintViolation(fn () => DB::table('user_profiles')->insert([
            'user_id' => $user->getKey(),
            'institution_name' => 'KUET',
            'institution_country_code' => 'bd',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $this->assertConstraintViolation(fn () => DB::table('onboarding_progress')->insert([
            'user_id' => $user->getKey(),
            'institution_state' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $this->assertConstraintViolation(fn () => DB::table('onboarding_course_drafts')->insert([
            'user_id' => $user->getKey(),
            'position' => 12,
            'title' => 'Outside the bounded draft list',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $this->assertConstraintViolation(fn () => DB::table('onboarding_intents')->insert([
            'user_id' => $user->getKey(),
            'kind' => 'hobby',
            'position' => 0,
            'text' => 'Not collected by onboarding',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $actions = $this->app->make(UpdateOnboardingStepAction::class);
        $actions->execute($user, OnboardingStep::Institution, [
            'skip' => false,
            'data' => ['institution_name' => 'KUET', 'institution_country_code' => 'BD'],
        ], 0);
        $actions->execute($user, OnboardingStep::Courses, [
            'skip' => false,
            'data' => ['courses' => [['title' => 'Software Engineering', 'code' => 'CSE 3200']]],
        ], 1);
        $actions->execute($user, OnboardingStep::Goals, [
            'skip' => false,
            'data' => ['goals' => ['Plan coursework'], 'problems' => []],
        ], 2);

        $user->deleteOrFail();

        foreach (['user_profiles', 'onboarding_progress', 'onboarding_course_drafts', 'onboarding_intents'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_database_enforces_cross_table_step_and_completion_consistency(): void
    {
        $user = User::factory()->create();
        $actions = $this->app->make(UpdateOnboardingStepAction::class);
        $version = 0;

        $version = $actions->execute($user, OnboardingStep::Institution, [
            'skip' => false,
            'data' => ['institution_name' => 'KUET', 'institution_country_code' => 'BD'],
        ], $version)->version;

        foreach ([
            OnboardingStep::Program,
            OnboardingStep::StudyStage,
            OnboardingStep::Courses,
            OnboardingStep::Goals,
            OnboardingStep::FirstSource,
        ] as $step) {
            $version = $actions->execute($user, $step, ['skip' => true, 'data' => []], $version)->version;
        }

        $this->assertSame(6, $version);
        $this->assertConstraintViolation(fn () => DB::table('onboarding_progress')
            ->where('user_id', $user->getKey())
            ->update(['completed_at' => now()]));
        $this->assertConstraintViolation(fn () => DB::table('onboarding_course_drafts')->insert([
            'user_id' => $user->getKey(),
            'position' => 0,
            'title' => 'Data that contradicts a skipped step',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $this->assertConstraintViolation(fn () => DB::table('onboarding_progress')
            ->where('user_id', $user->getKey())
            ->update(['courses_state' => 'completed']));
    }

    public function test_snapshot_reads_lock_progress_before_loading_private_children(): void
    {
        $user = User::factory()->create();
        $this->app->make(UpdateOnboardingStepAction::class)->execute(
            $user,
            OnboardingStep::Institution,
            [
                'skip' => false,
                'data' => ['institution_name' => 'KUET', 'institution_country_code' => 'BD'],
            ],
            0,
        );
        $queries = [];

        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->app->make(GetOnboardingSnapshot::class)->forUser($user);

        $this->assertTrue(array_any(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'onboarding_progress')
                && str_contains($sql, 'for share'),
        ));
    }

    public function test_aggregate_trigger_serializes_checks_and_rejects_ownership_moves(): void
    {
        $source = User::factory()->create();
        $destination = User::factory()->create();
        $actions = $this->app->make(UpdateOnboardingStepAction::class);

        foreach ([$source, $destination] as $user) {
            $actions->execute($user, OnboardingStep::Courses, [
                'skip' => false,
                'data' => ['courses' => [['title' => 'Owned course draft', 'code' => null]]],
            ], 0);
        }

        $this->assertConstraintViolation(fn () => DB::table('onboarding_course_drafts')
            ->where('user_id', $source->getKey())
            ->update([
                'user_id' => $destination->getKey(),
                'position' => 1,
            ]));

        $definition = DB::scalar(
            "SELECT pg_get_functiondef('enforce_onboarding_aggregate_consistency()'::regprocedure)",
        );
        $this->assertIsString($definition);
        $normalizedDefinition = strtolower($definition);
        $this->assertStringContainsString('for update', $normalizedDefinition);
        $this->assertStringContainsString('new.user_id is distinct from old.user_id', $normalizedDefinition);
    }

    public function test_persistence_failures_log_only_allowlisted_diagnostics(): void
    {
        $privateValue = 'private course title that must never reach logs';
        $user = User::factory()->create();
        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_course_drafts
            ADD CONSTRAINT onboarding_course_drafts_synthetic_failure
            CHECK (title <> 'private course title that must never reach logs')
            SQL);
        Log::spy();

        try {
            $this->app->make(UpdateOnboardingStepAction::class)->execute(
                $user,
                OnboardingStep::Courses,
                [
                    'skip' => false,
                    'data' => ['courses' => [['title' => $privateValue, 'code' => null]]],
                ],
                0,
            );
            self::fail('The synthetic database failure was not converted safely.');
        } catch (OnboardingPersistenceFailure $exception) {
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString($privateValue, $exception->getMessage());
        }

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Onboarding persistence failed.', Mockery::on(
                static fn (array $context): bool => $context['operation'] === 'step.update'
                    && $context['sql_state'] === '23514'
                    && $context['exception_type'] === QueryException::class
                    && is_string($context['request_id'])
                    && ! str_contains(serialize($context), $privateValue),
            ));
    }

    public function test_onboarding_migration_rolls_back_when_empty_and_refuses_to_erase_private_data(): void
    {
        $onboardingMigration = $this->migration('2026_07_14_000006_create_onboarding_foundation.php');
        $coursesMigration = $this->migration('2026_07_14_000007_create_courses_and_academic_terms.php');
        $plannerMigration = $this->migration('2026_07_14_000008_create_planner_foundation.php');
        $resourceMigration = $this->migration('2026_07_14_000009_create_resource_storage_foundation.php');
        // Phase 13 template copies reference courses(user_id, id).
        $templateMigration = $this->migration('2026_07_15_000012_create_templates_and_editable_copies.php');

        $templateMigration->down();
        $resourceMigration->down();
        $plannerMigration->down();
        $coursesMigration->down();
        $onboardingMigration->down();
        $this->assertFalse(Schema::hasTable('onboarding_progress'));
        $this->assertFalse(Schema::hasTable('user_profiles'));

        $onboardingMigration->up();
        $coursesMigration->up();
        $plannerMigration->up();
        $resourceMigration->up();
        $templateMigration->up();
        $this->assertTrue(Schema::hasTable('onboarding_progress'));
        $this->assertTrue(Schema::hasTable('user_profiles'));

        $user = User::factory()->create();
        DB::table('onboarding_progress')->insert([
            'user_id' => $user->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $onboardingMigration->down();
            self::fail('The onboarding migration erased private profile state.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('private profile data exists', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('onboarding_progress'));
        $this->assertDatabaseHas('onboarding_progress', ['user_id' => $user->getKey()]);
    }

    public function test_all_onboarding_datetimes_are_postgresql_timezone_aware(): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('table_name', [
                'user_profiles',
                'onboarding_progress',
                'onboarding_course_drafts',
                'onboarding_intents',
            ])
            ->whereIn('column_name', ['created_at', 'updated_at', 'completed_at', 'academic_materialized_at'])
            ->get(['table_name', 'column_name', 'data_type']);

        $this->assertCount(10, $columns);

        foreach ($columns as $column) {
            $this->assertSame(
                'timestamp with time zone',
                $column->data_type,
                "{$column->table_name}.{$column->column_name} must retain timezone information.",
            );
        }
    }

    private function migration(string $file): Migration
    {
        $migration = require database_path('migrations/'.$file);
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    /** @param  callable(): mixed  $mutation */
    private function assertConstraintViolation(callable $mutation): void
    {
        try {
            DB::transaction($mutation);
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0] ?? null);

            return;
        }

        self::fail('PostgreSQL accepted invalid onboarding data.');
    }
}
