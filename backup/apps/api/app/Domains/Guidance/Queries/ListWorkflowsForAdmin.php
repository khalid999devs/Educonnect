<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Queries;

use App\Domains\Guidance\Models\WorkflowRecipe;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListWorkflowsForAdmin
{
    /**
     * Every workflow recipe for curation - all lifecycle states, newest first.
     * Authorization is enforced at the route (content.curate).
     *
     * @return CursorPaginator<int, WorkflowRecipe>
     */
    public function execute(?string $state, int $perPage): CursorPaginator
    {
        $query = WorkflowRecipe::query()
            ->with(['category', 'steps'])
            ->select('workflow_recipes.*');

        if ($state !== null) {
            $query->where('state', $state);
        }

        return $query
            ->orderBy('public_id', 'desc')
            ->cursorPaginate($perPage)
            ->withQueryString();
    }
}
