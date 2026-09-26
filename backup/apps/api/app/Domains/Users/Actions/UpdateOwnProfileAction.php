<?php

declare(strict_types=1);

namespace App\Domains\Users\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Onboarding\Enums\OnboardingStep;
use App\Domains\Onboarding\Enums\OnboardingStepState;
use App\Domains\Onboarding\Models\OnboardingProgress;
use App\Domains\Onboarding\Queries\GetOnboardingSnapshot;
use App\Domains\Users\Data\SettingsSnapshot;
use App\Domains\Users\Exceptions\UserPersistenceFailure;
use App\Domains\Users\Models\User;
use App\Domains\Users\Queries\BuildOwnSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Post-onboarding edit path for the academic profile.
 *
 * This is deliberately a separate Action from UpdateOnboardingStepAction. That
 * action guards onboarding integrity (a completed profile may never be edited
 * into a state that could no longer complete) and that guard is not relaxed
 * here: this action re-asserts the very same invariant after writing, and it
 * additionally keeps onboarding_progress step states in sync with the profile
 * columns, because the enforce_onboarding_aggregate_consistency trigger couples
 * the two. Writing user_profiles without that sync raises SQLSTATE 23514.
 */
final readonly class UpdateOwnProfileAction
{
    private const AGGREGATE_CONSTRAINTS = 'user_profiles_onboarding_consistency, '
        .'onboarding_progress_aggregate_consistency';

    public function __construct(
        private GetOnboardingSnapshot $snapshots,
        private BuildOwnSettings $settings,
    ) {}

    /**
     * @param  array{institution_name: ?string, institution_country_code: ?string, department: ?string, degree: ?string, major: ?string, year_label: ?string, term_label: ?string}  $data
     */
    public function execute(User $user, array $data): SettingsSnapshot
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            DB::transaction(function () use ($user, $data): void {
                $now = now();
                DB::statement('SET CONSTRAINTS '.self::AGGREGATE_CONSTRAINTS.' DEFERRED');

                DB::table('onboarding_progress')->insertOrIgnore([
                    'user_id' => $user->getKey(),
                    'version' => 0,
                    'institution_state' => OnboardingStepState::Pending->value,
                    'program_state' => OnboardingStepState::Pending->value,
                    'study_stage_state' => OnboardingStepState::Pending->value,
                    'courses_state' => OnboardingStepState::Pending->value,
                    'goals_state' => OnboardingStepState::Pending->value,
                    'first_source_state' => OnboardingStepState::Pending->value,
                    'first_source_url' => null,
                    'first_source_title' => null,
                    'completed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $progress = OnboardingProgress::query()->lockForUpdate()->findOrFail($user->getKey());
                Gate::forUser($user)->authorize('update', $progress);

                $this->guardInstitution($progress, $data['institution_name']);

                DB::table('user_profiles')->insertOrIgnore([
                    'user_id' => $user->getKey(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('user_profiles')->where('user_id', $user->getKey())->update([
                    'institution_name' => $data['institution_name'],
                    'institution_country_code' => $data['institution_country_code'],
                    'department' => $data['department'],
                    'degree' => $data['degree'],
                    'major' => $data['major'],
                    'year_label' => $data['year_label'],
                    'term_label' => $data['term_label'],
                    'updated_at' => $now,
                ]);

                DB::table('onboarding_progress')
                    ->where('user_id', $user->getKey())
                    ->update([
                        'institution_state' => $this->stateFor(
                            $progress->stateFor(OnboardingStep::Institution),
                            $data['institution_name'] !== null,
                        )->value,
                        'program_state' => $this->stateFor(
                            $progress->stateFor(OnboardingStep::Program),
                            $data['department'] !== null || $data['degree'] !== null || $data['major'] !== null,
                        )->value,
                        'study_stage_state' => $this->stateFor(
                            $progress->stateFor(OnboardingStep::StudyStage),
                            $data['year_label'] !== null || $data['term_label'] !== null,
                        )->value,
                        'version' => $progress->version + 1,
                        'updated_at' => $now,
                    ]);

                $snapshot = $this->snapshots->forUser($user);

                // The same invariant UpdateOnboardingStepAction enforces, restated
                // rather than relaxed: a completed profile stays completable.
                if ($progress->completed_at !== null && ! $snapshot->canComplete) {
                    throw ValidationException::withMessages([
                        'profile' => ['This change would invalidate the completed onboarding profile.'],
                    ]);
                }

                DB::statement('SET CONSTRAINTS '.self::AGGREGATE_CONSTRAINTS.' IMMEDIATE');
            }, 3);
        } catch (QueryException $exception) {
            throw UserPersistenceFailure::fromQueryException($exception, 'settings.profile.update');
        }

        return $this->settings->execute($user);
    }

    private function guardInstitution(OnboardingProgress $progress, ?string $institutionName): void
    {
        if ($institutionName !== null) {
            return;
        }

        if ($progress->stateFor(OnboardingStep::Institution) === OnboardingStepState::Completed) {
            throw ValidationException::withMessages([
                'institution_name' => ['The institution cannot be removed once it has been recorded.'],
            ]);
        }
    }

    private function stateFor(OnboardingStepState $current, bool $hasValue): OnboardingStepState
    {
        if ($hasValue) {
            return OnboardingStepState::Completed;
        }

        return $current === OnboardingStepState::Completed
            ? OnboardingStepState::Skipped
            : $current;
    }
}
