<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Actions\MaterializeOnboardingWorkspaceAction;
use App\Domains\Onboarding\Data\OnboardingSnapshot;
use App\Domains\Onboarding\Exceptions\OnboardingPersistenceFailure;
use App\Domains\Onboarding\Exceptions\OnboardingVersionConflict;
use App\Domains\Onboarding\Models\OnboardingProgress;
use App\Domains\Onboarding\Queries\GetOnboardingSnapshot;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CompleteOnboardingAction
{
    public function __construct(
        private GetOnboardingSnapshot $snapshots,
        private MaterializeOnboardingWorkspaceAction $materializeWorkspace,
    ) {}

    public function execute(User $user, int $expectedVersion): OnboardingSnapshot
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            return DB::transaction(function () use ($user, $expectedVersion): OnboardingSnapshot {
                $progress = OnboardingProgress::query()->lockForUpdate()->find($user->getKey());

                if (! $progress instanceof OnboardingProgress) {
                    throw ValidationException::withMessages([
                        'onboarding' => ['Complete the required onboarding steps first.'],
                    ]);
                }

                Gate::forUser($user)->authorize('complete', $progress);

                if ($progress->completed_at !== null) {
                    $this->materializeWorkspace->execute($user);

                    return $this->snapshots->forUser($user);
                }

                if ($progress->version !== $expectedVersion) {
                    throw new OnboardingVersionConflict;
                }

                $snapshot = $this->snapshots->forUser($user);

                if (! $snapshot->canComplete) {
                    throw ValidationException::withMessages([
                        'onboarding' => [
                            'Complete the institution step, resolve every optional step, and provide a course, goal, or problem.',
                        ],
                    ]);
                }

                DB::table('onboarding_progress')
                    ->where('user_id', $user->getKey())
                    ->update([
                        'completed_at' => now(),
                        'version' => $progress->version + 1,
                        'updated_at' => now(),
                    ]);

                $this->materializeWorkspace->execute($user);

                return $this->snapshots->forUser($user);
            }, 3);
        } catch (QueryException $exception) {
            throw OnboardingPersistenceFailure::fromQueryException($exception, 'completion.update');
        }
    }
}
