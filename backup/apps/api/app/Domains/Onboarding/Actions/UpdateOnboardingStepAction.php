<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Onboarding\Data\OnboardingSnapshot;
use App\Domains\Onboarding\Enums\OnboardingStep;
use App\Domains\Onboarding\Enums\OnboardingStepState;
use App\Domains\Onboarding\Exceptions\OnboardingPersistenceFailure;
use App\Domains\Onboarding\Exceptions\OnboardingVersionConflict;
use App\Domains\Onboarding\Models\OnboardingProgress;
use App\Domains\Onboarding\Queries\GetOnboardingSnapshot;
use App\Domains\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

final readonly class UpdateOnboardingStepAction
{
    private const AGGREGATE_CONSTRAINTS = 'user_profiles_onboarding_consistency, '
        .'onboarding_progress_aggregate_consistency, '
        .'onboarding_course_drafts_aggregate_consistency, '
        .'onboarding_intents_aggregate_consistency';

    public function __construct(private GetOnboardingSnapshot $snapshots) {}

    /**
     * @param  array{skip: bool, data: array<string, mixed>}  $payload
     */
    public function execute(
        User $user,
        OnboardingStep $step,
        #[SensitiveParameter] array $payload,
        int $expectedVersion,
    ): OnboardingSnapshot {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        if ($payload['skip'] && ! $step->isSkippable()) {
            throw ValidationException::withMessages([
                'state' => ['The institution step cannot be skipped.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $step, $payload, $expectedVersion): OnboardingSnapshot {
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

                $desiredState = $payload['skip']
                    ? OnboardingStepState::Skipped
                    : OnboardingStepState::Completed;
                $desiredData = $this->canonicalData($step, $payload['data']);

                if ($progress->stateFor($step) === $desiredState
                    && $this->currentData($user, $progress, $step) === $desiredData) {
                    DB::statement('SET CONSTRAINTS '.self::AGGREGATE_CONSTRAINTS.' IMMEDIATE');

                    return $this->snapshots->forUser($user);
                }

                if ($progress->version !== $expectedVersion) {
                    throw new OnboardingVersionConflict;
                }

                $this->replaceStepData($user, $step, $desiredState, $desiredData, $now);

                $progressUpdate = [
                    $step->stateColumn() => $desiredState->value,
                    'version' => $progress->version + 1,
                    'updated_at' => $now,
                ];

                if ($step === OnboardingStep::FirstSource) {
                    $progressUpdate['first_source_url'] = $desiredState === OnboardingStepState::Skipped
                        ? null
                        : $desiredData['url'];
                    $progressUpdate['first_source_title'] = $desiredState === OnboardingStepState::Skipped
                        ? null
                        : $desiredData['title'];
                }

                DB::table('onboarding_progress')
                    ->where('user_id', $user->getKey())
                    ->update($progressUpdate);

                $snapshot = $this->snapshots->forUser($user);

                if ($progress->completed_at !== null && ! $snapshot->canComplete) {
                    throw ValidationException::withMessages([
                        'state' => ['This change would invalidate the completed onboarding profile.'],
                    ]);
                }

                DB::statement('SET CONSTRAINTS '.self::AGGREGATE_CONSTRAINTS.' IMMEDIATE');

                return $snapshot;
            }, 3);
        } catch (QueryException $exception) {
            throw OnboardingPersistenceFailure::fromQueryException($exception, 'step.update');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function canonicalData(OnboardingStep $step, array $data): array
    {
        return match ($step) {
            OnboardingStep::Institution => [
                'institution_name' => $data['institution_name'] ?? null,
                'institution_country_code' => $data['institution_country_code'] ?? null,
            ],
            OnboardingStep::Program => [
                'department' => $data['department'] ?? null,
                'degree' => $data['degree'] ?? null,
                'major' => $data['major'] ?? null,
            ],
            OnboardingStep::StudyStage => [
                'year_label' => $data['year_label'] ?? null,
                'term_label' => $data['term_label'] ?? null,
            ],
            OnboardingStep::Courses => [
                'courses' => array_values(array_map(static fn (array $course): array => [
                    'title' => $course['title'],
                    'code' => $course['code'] ?? null,
                ], $data['courses'] ?? [])),
            ],
            OnboardingStep::Goals => [
                'goals' => array_values($data['goals'] ?? []),
                'problems' => array_values($data['problems'] ?? []),
            ],
            OnboardingStep::FirstSource => [
                'url' => $data['url'] ?? null,
                'title' => $data['title'] ?? null,
            ],
        };
    }

    /** @return array<string, mixed> */
    private function currentData(User $user, OnboardingProgress $progress, OnboardingStep $step): array
    {
        if ($progress->stateFor($step) === OnboardingStepState::Skipped) {
            return $this->canonicalData($step, []);
        }

        $profile = DB::table('user_profiles')->where('user_id', $user->getKey())->first();

        return match ($step) {
            OnboardingStep::Institution => [
                'institution_name' => $profile?->institution_name,
                'institution_country_code' => $profile?->institution_country_code,
            ],
            OnboardingStep::Program => [
                'department' => $profile?->department,
                'degree' => $profile?->degree,
                'major' => $profile?->major,
            ],
            OnboardingStep::StudyStage => [
                'year_label' => $profile?->year_label,
                'term_label' => $profile?->term_label,
            ],
            OnboardingStep::Courses => [
                'courses' => DB::table('onboarding_course_drafts')
                    ->where('user_id', $user->getKey())
                    ->orderBy('position')
                    ->get(['title', 'code'])
                    ->map(static fn (object $course): array => [
                        'title' => (string) $course->title,
                        'code' => is_string($course->code) ? $course->code : null,
                    ])->all(),
            ],
            OnboardingStep::Goals => [
                'goals' => DB::table('onboarding_intents')
                    ->where('user_id', $user->getKey())
                    ->where('kind', 'goal')
                    ->orderBy('position')
                    ->pluck('text')
                    ->map(static fn ($text): string => (string) $text)
                    ->all(),
                'problems' => DB::table('onboarding_intents')
                    ->where('user_id', $user->getKey())
                    ->where('kind', 'problem')
                    ->orderBy('position')
                    ->pluck('text')
                    ->map(static fn ($text): string => (string) $text)
                    ->all(),
            ],
            OnboardingStep::FirstSource => [
                'url' => $progress->first_source_url,
                'title' => $progress->first_source_title,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function replaceStepData(
        User $user,
        OnboardingStep $step,
        OnboardingStepState $state,
        array $data,
        CarbonInterface $now,
    ): void {
        match ($step) {
            OnboardingStep::Institution => $this->replaceProfileFields($user, [
                'institution_name' => $data['institution_name'],
                'institution_country_code' => $data['institution_country_code'],
            ], $now),
            OnboardingStep::Program => $this->replaceProfileFields($user, [
                'department' => $state === OnboardingStepState::Skipped ? null : $data['department'],
                'degree' => $state === OnboardingStepState::Skipped ? null : $data['degree'],
                'major' => $state === OnboardingStepState::Skipped ? null : $data['major'],
            ], $now, $state !== OnboardingStepState::Skipped),
            OnboardingStep::StudyStage => $this->replaceProfileFields($user, [
                'year_label' => $state === OnboardingStepState::Skipped ? null : $data['year_label'],
                'term_label' => $state === OnboardingStepState::Skipped ? null : $data['term_label'],
            ], $now, $state !== OnboardingStepState::Skipped),
            OnboardingStep::Courses => $this->replaceCourses($user, $state, $data['courses'], $now),
            OnboardingStep::Goals => $this->replaceIntents($user, $state, $data, $now),
            OnboardingStep::FirstSource => null,
        };
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function replaceProfileFields(User $user, array $fields, CarbonInterface $now, bool $create = true): void
    {
        if ($create) {
            DB::table('user_profiles')->insertOrIgnore([
                'user_id' => $user->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (DB::table('user_profiles')->where('user_id', $user->getKey())->exists()) {
            DB::table('user_profiles')->where('user_id', $user->getKey())->update([
                ...$fields,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param  list<array{title: string, code: ?string}>  $courses */
    private function replaceCourses(
        User $user,
        OnboardingStepState $state,
        array $courses,
        CarbonInterface $now,
    ): void {
        DB::table('onboarding_course_drafts')->where('user_id', $user->getKey())->delete();

        if ($state === OnboardingStepState::Skipped) {
            return;
        }

        DB::table('onboarding_course_drafts')->insert(array_map(
            static fn (array $course, int $position): array => [
                'user_id' => $user->getKey(),
                'position' => $position,
                'title' => $course['title'],
                'code' => $course['code'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $courses,
            array_keys($courses),
        ));
    }

    /** @param  array{goals: list<string>, problems: list<string>}  $data */
    private function replaceIntents(
        User $user,
        OnboardingStepState $state,
        array $data,
        CarbonInterface $now,
    ): void {
        DB::table('onboarding_intents')->where('user_id', $user->getKey())->delete();

        if ($state === OnboardingStepState::Skipped) {
            return;
        }

        $rows = [];

        foreach (['goal' => $data['goals'], 'problem' => $data['problems']] as $kind => $values) {
            foreach ($values as $position => $text) {
                $rows[] = [
                    'user_id' => $user->getKey(),
                    'kind' => $kind,
                    'position' => $position,
                    'text' => $text,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('onboarding_intents')->insert($rows);
        }
    }
}
