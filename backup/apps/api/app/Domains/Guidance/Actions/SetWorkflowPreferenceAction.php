<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Actions;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Guidance\Exceptions\GuidancePersistenceFailure;
use App\Domains\Guidance\Models\UserWorkflowPreference;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Queries\FindPublishedWorkflow;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SetWorkflowPreferenceAction
{
    public function __construct(private FindPublishedWorkflow $workflows) {}

    public function execute(User $user, string $publicId, GuidancePreferenceState $state): WorkflowRecipe
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $state): WorkflowRecipe {
                User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $workflow = $this->workflows->execute($user, $publicId, lockForUpdate: true);

                $preference = UserWorkflowPreference::query()
                    ->where('user_id', $user->getKey())
                    ->where('workflow_recipe_id', $workflow->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($preference instanceof UserWorkflowPreference) {
                    Gate::forUser($user)->authorize('update', $preference);
                    $current = $preference->getAttribute('state');
                    $currentValue = $current instanceof GuidancePreferenceState ? $current : GuidancePreferenceState::tryFrom((string) $current);

                    if ($currentValue === $state) {
                        $workflow->setAttribute('viewer_preference_state', $state->value);

                        return $workflow;
                    }

                    $preference->forceFill(['state' => $state])->save();
                    $workflow->setAttribute('viewer_preference_state', $state->value);

                    return $workflow;
                }

                Gate::forUser($user)->authorize('create', UserWorkflowPreference::class);

                $preference = new UserWorkflowPreference;
                $preference->forceFill([
                    'user_id' => $user->getKey(),
                    'workflow_recipe_id' => $workflow->getKey(),
                    'state' => $state,
                ])->save();
                $workflow->setAttribute('viewer_preference_state', $state->value);

                return $workflow;
            }, 3);
        } catch (QueryException $exception) {
            throw GuidancePersistenceFailure::fromQueryException($exception, 'workflow.preference.set');
        }
    }
}
