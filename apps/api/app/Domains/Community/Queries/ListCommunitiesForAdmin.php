<?php

declare(strict_types=1);

namespace App\Domains\Community\Queries;

use App\Domains\Community\Models\Community;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListCommunitiesForAdmin
{
    /**
     * Every community for management — all visibilities (unlike the student
     * directory, which shows published only), newest first. Authorization is
     * enforced at the route (content.curate).
     *
     * @return CursorPaginator<int, Community>
     */
    public function execute(?string $visibility, int $perPage): CursorPaginator
    {
        $query = Community::query()
            ->withCount('memberships')
            ->select('communities.*');

        if ($visibility !== null) {
            $query->where('visibility', $visibility);
        }

        return $query
            ->orderBy('public_id', 'desc')
            ->cursorPaginate($perPage)
            ->withQueryString();
    }
}
