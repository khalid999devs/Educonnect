<?php

declare(strict_types=1);

namespace Tests\Feature\Courses;

use App\Domains\Courses\Actions\MaterializeOnboardingWorkspaceAction;
use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Models\Course;
use App\Domains\Onboarding\Actions\CompleteOnboardingAction;
use App\Domains\Onboarding\Actions\UpdateOnboardingStepAction;
use App\Domains\Onboarding\Enums\OnboardingStep;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OnboardingCourseMaterializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_completion_materializes_one_term_and_ordered_course_drafts_exactly_once(): void
    {
        $user = User::factory()->create();
        $version = $this->prepareCourseOnboarding($user);

        $completed = $this->app->make(CompleteOnboardingAction::class)->execute($user, $version);

        $this->assertSame(7, $completed->version);
        $this->assertNotNull($completed->completedAt);
        $term = AcademicTerm::query()->where('user_id', $user->getKey())->sole();
        $courses = Course::query()
            ->where('user_id', $user->getKey())
            ->orderBy('onboarding_position')
            ->get();

        $this->assertSame('Term 2', $term->label);
        $this->assertSame(1, $term->version);
        $this->assertCount(2, $courses);
        $this->assertSame(['Data Structures', 'Software Engineering'], $courses->pluck('title')->all());
        $this->assertSame([0, 1], $courses->pluck('onboarding_position')->all());
        $this->assertSame([$term->getKey(), $term->getKey()], $courses->pluck('academic_term_id')->all());
        $this->assertSame([1, 1], $courses->pluck('version')->all());
        $this->assertNotNull(
            DB::table('onboarding_progress')->where('user_id', $user->getKey())->value('academic_materialized_at'),
        );

        $completedAt = $completed->completedAt?->toISOString();
        $materializedAt = DB::table('onboarding_progress')
            ->where('user_id', $user->getKey())
            ->value('academic_materialized_at');

        $retry = $this->app->make(CompleteOnboardingAction::class)->execute($user, $version);

        $this->assertSame(7, $retry->version);
        $this->assertSame($completedAt, $retry->completedAt?->toISOString());
        $this->assertSame(
            $materializedAt,
            DB::table('onboarding_progress')
                ->where('user_id', $user->getKey())
                ->value('academic_materialized_at'),
        );
        $this->assertDatabaseCount('academic_terms', 1);
        $this->assertDatabaseCount('courses', 2);
        $this->assertFalse($this->app->make(MaterializeOnboardingWorkspaceAction::class)->execute($user));
    }

    public function test_later_onboarding_edits_never_resynchronize_materialized_terms_or_courses(): void
    {
        $user = User::factory()->create();
        $version = $this->prepareCourseOnboarding($user);
        $snapshot = $this->app->make(CompleteOnboardingAction::class)->execute($user, $version);
        $originalTerm = AcademicTerm::query()->where('user_id', $user->getKey())->sole();
        $originalCourseIds = Course::query()
            ->where('user_id', $user->getKey())
            ->orderBy('onboarding_position')
            ->pluck('public_id')
            ->all();
        $updates = $this->app->make(UpdateOnboardingStepAction::class);

        $snapshot = $updates->execute($user, OnboardingStep::Courses, [
            'skip' => false,
            'data' => [
                'courses' => [
                    ['title' => 'Changed onboarding draft', 'code' => 'NEW 101'],
                ],
            ],
        ], $snapshot->version);
        $snapshot = $updates->execute($user, OnboardingStep::StudyStage, [
            'skip' => false,
            'data' => ['year_label' => 'Year 4', 'term_label' => 'Term 3'],
        ], $snapshot->version);

        $this->app->make(CompleteOnboardingAction::class)->execute($user, $snapshot->version);

        $this->assertSame('Term 2', $originalTerm->fresh()?->label);
        $this->assertSame(
            $originalCourseIds,
            Course::query()
                ->where('user_id', $user->getKey())
                ->orderBy('onboarding_position')
                ->pluck('public_id')
                ->all(),
        );
        $this->assertSame(
            ['Data Structures', 'Software Engineering'],
            Course::query()
                ->where('user_id', $user->getKey())
                ->orderBy('onboarding_position')
                ->pluck('title')
                ->all(),
        );
        $this->assertDatabaseHas('onboarding_course_drafts', [
            'user_id' => $user->getKey(),
            'title' => 'Changed onboarding draft',
        ]);
        $this->assertDatabaseCount('academic_terms', 1);
        $this->assertDatabaseCount('courses', 2);
    }

    public function test_course_drafts_without_a_term_label_materialize_as_unassigned_owned_courses(): void
    {
        $user = User::factory()->create();
        $updates = $this->app->make(UpdateOnboardingStepAction::class);
        $version = 0;
        $version = $updates->execute($user, OnboardingStep::Institution, [
            'skip' => false,
            'data' => ['institution_name' => 'KUET', 'institution_country_code' => 'BD'],
        ], $version)->version;
        $version = $updates->execute(
            $user,
            OnboardingStep::Program,
            ['skip' => true, 'data' => []],
            $version,
        )->version;
        $version = $updates->execute(
            $user,
            OnboardingStep::StudyStage,
            ['skip' => true, 'data' => []],
            $version,
        )->version;
        $version = $updates->execute($user, OnboardingStep::Courses, [
            'skip' => false,
            'data' => ['courses' => [['title' => 'Independent Study', 'code' => null]]],
        ], $version)->version;
        $version = $updates->execute(
            $user,
            OnboardingStep::Goals,
            ['skip' => true, 'data' => []],
            $version,
        )->version;
        $version = $updates->execute(
            $user,
            OnboardingStep::FirstSource,
            ['skip' => true, 'data' => []],
            $version,
        )->version;

        $this->app->make(CompleteOnboardingAction::class)->execute($user, $version);

        $this->assertDatabaseCount('academic_terms', 0);
        $this->assertDatabaseHas('courses', [
            'user_id' => $user->getKey(),
            'academic_term_id' => null,
            'title' => 'Independent Study',
            'onboarding_position' => 0,
        ]);
    }

    public function test_bounded_materialization_command_resumes_completed_legacy_backlog(): void
    {
        $users = User::factory()->count(2)->create();

        foreach ($users as $user) {
            $version = $this->prepareCourseOnboarding($user);
            $this->app->make(CompleteOnboardingAction::class)->execute($user, $version);
        }

        Course::query()->whereIn('user_id', $users->modelKeys())->delete();
        AcademicTerm::query()->whereIn('user_id', $users->modelKeys())->delete();
        DB::table('onboarding_progress')
            ->whereIn('user_id', $users->modelKeys())
            ->update(['academic_materialized_at' => null]);

        $this->artisan('courses:materialize-onboarding', ['--limit' => 1])
            ->expectsOutput('Materialized 1 of 1 inspected onboarding aggregates.')
            ->assertExitCode(0);
        $this->assertDatabaseCount('academic_terms', 1);
        $this->assertDatabaseCount('courses', 2);
        $this->assertSame(
            1,
            DB::table('onboarding_progress')->whereNotNull('academic_materialized_at')->count(),
        );

        $this->artisan('courses:materialize-onboarding', ['--limit' => 100])
            ->expectsOutput('Materialized 1 of 1 inspected onboarding aggregates.')
            ->assertExitCode(0);
        $this->assertDatabaseCount('academic_terms', 2);
        $this->assertDatabaseCount('courses', 4);
        $this->assertSame(
            2,
            DB::table('onboarding_progress')->whereNotNull('academic_materialized_at')->count(),
        );

        $this->artisan('courses:materialize-onboarding', ['--limit' => 0])
            ->expectsOutput('The --limit option must be an integer between 1 and 1000.')
            ->assertExitCode(1);
    }

    private function prepareCourseOnboarding(User $user): int
    {
        $updates = $this->app->make(UpdateOnboardingStepAction::class);
        $version = 0;
        $version = $updates->execute($user, OnboardingStep::Institution, [
            'skip' => false,
            'data' => ['institution_name' => 'KUET', 'institution_country_code' => 'BD'],
        ], $version)->version;
        $version = $updates->execute(
            $user,
            OnboardingStep::Program,
            ['skip' => true, 'data' => []],
            $version,
        )->version;
        $version = $updates->execute($user, OnboardingStep::StudyStage, [
            'skip' => false,
            'data' => ['year_label' => 'Year 3', 'term_label' => 'Term 2'],
        ], $version)->version;
        $version = $updates->execute($user, OnboardingStep::Courses, [
            'skip' => false,
            'data' => [
                'courses' => [
                    ['title' => 'Data Structures', 'code' => 'CSE 2101'],
                    ['title' => 'Software Engineering', 'code' => 'CSE 3200'],
                ],
            ],
        ], $version)->version;
        $version = $updates->execute(
            $user,
            OnboardingStep::Goals,
            ['skip' => true, 'data' => []],
            $version,
        )->version;

        return $updates->execute(
            $user,
            OnboardingStep::FirstSource,
            ['skip' => true, 'data' => []],
            $version,
        )->version;
    }
}
