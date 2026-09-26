<?php

declare(strict_types=1);

namespace App\Domains\Templates\Queries;

use App\Domains\Templates\Models\Template;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListTemplatesForAdmin
{
    /**
     * Every template for curation - all lifecycle states, newest first, with the
     * latest version. Authorization is enforced at the route (content.curate).
     *
     * @return CursorPaginator<int, Template>
     */
    public function execute(?string $state, int $perPage): CursorPaginator
    {
        $query = Template::query()
            ->with(['category', 'latestVersion'])
            ->select('templates.*');

        if ($state !== null) {
            $query->where('state', $state);
        }

        return $query
            ->orderBy('public_id', 'desc')
            ->cursorPaginate($perPage)
            ->withQueryString();
    }
}
