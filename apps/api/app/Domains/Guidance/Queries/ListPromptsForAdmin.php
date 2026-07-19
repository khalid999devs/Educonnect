<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Queries;

use App\Domains\Guidance\Models\PromptTemplate;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListPromptsForAdmin
{
    /**
     * Every prompt template for curation - all lifecycle states, newest first
     * (ULID order). Authorization is enforced at the route (content.curate).
     *
     * @return CursorPaginator<int, PromptTemplate>
     */
    public function execute(?string $state, int $perPage): CursorPaginator
    {
        $query = PromptTemplate::query()
            ->with(['category', 'relatedTools'])
            ->select('prompt_templates.*');

        if ($state !== null) {
            $query->where('state', $state);
        }

        return $query
            ->orderBy('public_id', 'desc')
            ->cursorPaginate($perPage)
            ->withQueryString();
    }
}
