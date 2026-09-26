<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Guidance\Exceptions\GuidancePersistenceFailure;
use App\Domains\Guidance\Models\UserWorkflowPreference;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Support\PublishedWorkflowVisibility;
use App\Domains\Guidance\Support\WorkflowCursorSort;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ListPublishedWorkflows
{
    /** @return CursorPaginator<int, WorkflowRecipe> */
    public function execute(
        User $user,
        ?string $search,
        ?string $category,
        string $preference,
        string $sort,
        int $perPage,
    ): CursorPaginator {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        if ($perPage < 1 || $perPage > 50) {
            throw new InvalidArgumentException('Workflow page size must be between 1 and 50.');
        }

        try {
            $sortDefinition = WorkflowCursorSort::for($sort);
            $query = WorkflowRecipe::query()
                ->select('workflow_recipes.*')
                ->selectRaw($sortDefinition['expression'].' as '.$sortDefinition['cursor_column'])
                ->addSelect([
                    'viewer_preference_state' => UserWorkflowPreference::query()
                        ->select('state')
                        ->whereColumn('user_workflow_preferences.workflow_recipe_id', 'workflow_recipes.id')
                        ->where('user_workflow_preferences.user_id', $user->getKey())
                        ->limit(1),
                ])
                ->with(['category', 'steps', 'steps.tool', 'steps.promptTemplate', 'steps.template']);

            PublishedWorkflowVisibility::apply($query);

            if ($category !== null) {
                $query->whereHas(
                    'category',
                    static fn (Builder $categories): Builder => $categories->where('slug', $category),
                );
            }

            if ($search !== null) {
                $query->whereRaw(
                    "LOWER(CONCAT_WS(' ', workflow_recipes.title, workflow_recipes.goal, workflow_recipes.expected_outcome, workflow_recipes.integrity_note, workflow_recipes.provenance)) LIKE ? ESCAPE '\\'",
                    [$this->containsPattern($search)],
                );
            }

            $this->applyPreferenceFilter($query, (int) $user->getKey(), $preference);

            return $query
                ->orderBy($sortDefinition['cursor_column'], $sortDefinition['direction'])
                ->orderBy('public_id', $sortDefinition['direction'])
                ->cursorPaginate($perPage)
                ->withQueryString();
        } catch (QueryException $exception) {
            throw GuidancePersistenceFailure::fromQueryException($exception, 'workflow.list');
        }
    }

    /** @param Builder<WorkflowRecipe> $query */
    private function applyPreferenceFilter(Builder $query, int $userId, string $preference): void
    {
        if ($preference === 'all') {
            return;
        }

        if ($preference === 'none') {
            $query->whereNotExists(static function (QueryBuilder $preferences) use ($userId): void {
                $preferences
                    ->selectRaw('1')
                    ->from('user_workflow_preferences')
                    ->whereColumn('user_workflow_preferences.workflow_recipe_id', 'workflow_recipes.id')
                    ->where('user_workflow_preferences.user_id', $userId);
            });

            return;
        }

        $state = GuidancePreferenceState::tryFrom($preference);

        if (! $state instanceof GuidancePreferenceState) {
            throw new InvalidArgumentException('Unsupported workflow preference filter.');
        }

        $query->whereExists(static function (QueryBuilder $preferences) use ($userId, $state): void {
            $preferences
                ->selectRaw('1')
                ->from('user_workflow_preferences')
                ->whereColumn('user_workflow_preferences.workflow_recipe_id', 'workflow_recipes.id')
                ->where('user_workflow_preferences.user_id', $userId)
                ->where('user_workflow_preferences.state', $state->value);
        });
    }

    private function containsPattern(string $search): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search));

        return '%'.$escaped.'%';
    }
}
