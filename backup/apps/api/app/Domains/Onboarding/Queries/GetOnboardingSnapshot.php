<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Onboarding\Data\OnboardingSnapshot;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Enums\OnboardingStep;
use App\Domains\Onboarding\Enums\OnboardingStepState;
use App\Domains\Onboarding\Exceptions\OnboardingPersistenceFailure;
use App\Domains\Onboarding\Models\OnboardingCourseDraft;
use App\Domains\Onboarding\Models\OnboardingIntent;
use App\Domains\Onboarding\Models\OnboardingProgress;
use App\Domains\Onboarding\Models\UserProfile;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class GetOnboardingSnapshot
{
    public function forUser(User $user): OnboardingSnapshot
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            return DB::transaction(fn (): OnboardingSnapshot => $this->readLockedSnapshot($user), 3);
        } catch (QueryException $exception) {
            throw OnboardingPersistenceFailure::fromQueryException($exception, 'snapshot.read');
        }
    }

    private function readLockedSnapshot(User $user): OnboardingSnapshot
    {
        $progress = OnboardingProgress::query()->sharedLock()->find($user->getKey());

        if (! $progress instanceof OnboardingProgress) {
            return $this->emptySnapshot();
        }

        Gate::forUser($user)->authorize('view', $progress);

        $version = $progress->version;
        $completedAt = $progress->completed_at;

        $profile = UserProfile::query()->find($user->getKey());
        $steps = [];

        foreach (OnboardingStep::cases() as $step) {
            $steps[$step->value] = $progress->stateFor($step);
        }

        $courses = OnboardingCourseDraft::query()
            ->where('user_id', $user->getKey())
            ->orderBy('position')
            ->get(['title', 'code'])
            ->map(static fn (OnboardingCourseDraft $course): array => [
                'title' => $course->title,
                'code' => $course->code,
            ])
            ->values()
            ->all();
        $intents = OnboardingIntent::query()
            ->where('user_id', $user->getKey())
            ->orderBy('kind')
            ->orderBy('position')
            ->get(['kind', 'text']);
        $goals = $intents->where('kind', 'goal')->pluck('text')->values()->all();
        $problems = $intents->where('kind', 'problem')->pluck('text')->values()->all();
        $firstSource = $progress->stateFor(OnboardingStep::FirstSource) === OnboardingStepState::Completed
            ? [
                'url' => (string) $progress->first_source_url,
                'title' => is_string($progress->first_source_title) ? $progress->first_source_title : null,
            ]
            : null;
        $currentStep = null;

        foreach (OnboardingStep::cases() as $step) {
            if ($steps[$step->value] === OnboardingStepState::Pending) {
                $currentStep = $step;
                break;
            }
        }

        $status = $progress->completed_at === null ? OnboardingStatus::InProgress : OnboardingStatus::Completed;
        $hasStarterSeed = $courses !== [] || $goals !== [] || $problems !== [];
        $canComplete = $steps[OnboardingStep::Institution->value] === OnboardingStepState::Completed
            && array_all($steps, static fn (OnboardingStepState $state): bool => $state->isTerminal())
            && $hasStarterSeed;

        if ($currentStep === null && $status !== OnboardingStatus::Completed && ! $hasStarterSeed) {
            $currentStep = OnboardingStep::Courses;
        }

        return new OnboardingSnapshot(
            version: $version,
            status: $status,
            currentStep: $status === OnboardingStatus::Completed ? null : $currentStep,
            canComplete: $canComplete,
            completedAt: $completedAt,
            steps: $steps,
            profile: [
                'institution_name' => $profile?->institution_name,
                'institution_country_code' => $profile?->institution_country_code,
                'department' => $profile?->department,
                'degree' => $profile?->degree,
                'major' => $profile?->major,
                'year_label' => $profile?->year_label,
                'term_label' => $profile?->term_label,
            ],
            courses: $courses,
            goals: $goals,
            problems: $problems,
            firstSource: $firstSource,
        );
    }

    private function emptySnapshot(): OnboardingSnapshot
    {
        $steps = [];

        foreach (OnboardingStep::cases() as $step) {
            $steps[$step->value] = OnboardingStepState::Pending;
        }

        return new OnboardingSnapshot(
            version: 0,
            status: OnboardingStatus::NotStarted,
            currentStep: OnboardingStep::Institution,
            canComplete: false,
            completedAt: null,
            steps: $steps,
            profile: [
                'institution_name' => null,
                'institution_country_code' => null,
                'department' => null,
                'degree' => null,
                'major' => null,
                'year_label' => null,
                'term_label' => null,
            ],
            courses: [],
            goals: [],
            problems: [],
            firstSource: null,
        );
    }
}
