<?php

declare(strict_types=1);

namespace App\Domains\Tools\Queries;

use App\Domains\Tools\Models\Tool;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListToolsForAdmin
{
    /**
     * Every tool for curation - all lifecycle states, newest first (ULID order).
     * Authorization is enforced at the route (content.curate).
     *
     * @return CursorPaginator<int, Tool>
     */
    public function execute(?string $state, int $perPage): CursorPaginator
    {
        $query = Tool::query()->with('category')->select('tools.*');

        if ($state !== null) {
            $query->where('state', $state);
        }

        return $query
            ->orderBy('public_id', 'desc')
            ->cursorPaginate($perPage)
            ->withQueryString();
    }
}
