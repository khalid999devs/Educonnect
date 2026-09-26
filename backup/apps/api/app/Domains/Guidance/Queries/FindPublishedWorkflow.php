<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Exceptions\GuidancePersistenceFailure;
use App\Domains\Guidance\Models\UserWorkflowPreference;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Support\PublishedWorkflowVisibility;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindPublishedWorkflow
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): WorkflowRecipe
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = WorkflowRecipe::query()
                ->select('workflow_recipes.*')
                ->addSelect([
                    'viewer_preference_state' => UserWorkflowPreference::query()
                        ->select('state')
                        ->whereColumn('user_workflow_preferences.workflow_recipe_id', 'workflow_recipes.id')
                        ->where('user_workflow_preferences.user_id', $user->getKey())
                        ->limit(1),
                ])
                ->where('workflow_recipes.public_id', $publicId)
                ->with(['category', 'steps', 'steps.tool', 'steps.promptTemplate', 'steps.template']);

            PublishedWorkflowVisibility::apply($query);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $workflow = $query->first();

            if (! $workflow instanceof WorkflowRecipe) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $workflow);

            return $workflow;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw GuidancePersistenceFailure::fromQueryException($exception, 'workflow.read');
        }
    }
}
