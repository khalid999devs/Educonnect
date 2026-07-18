<?php

declare(strict_types=1);

namespace App\Domains\Users\Queries;

use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final class ListUsers
{
    /**
     * The admin user directory, newest-first. Search matches name or email;
     * filters narrow by role membership and account status. No private academic
     * content is exposed — only account-level fields (doc 08).
     *
     * @return CursorPaginator<int, User>
     */
    public function execute(
        ?string $search,
        ?string $role,
        ?string $status,
        int $perPage,
    ): CursorPaginator {
        $query = User::query()
            ->with('roles')
            ->select(['users.*', 'users.created_at as cursor_created_at_desc']);

        if ($search !== null) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
            $query->where(static function (Builder $matches) use ($like): void {
                $matches->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        if ($role !== null) {
            $query->whereHas('roles', static fn (Builder $roles) => $roles->where('roles.key', $role));
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query
            ->orderBy('cursor_created_at_desc', 'desc')
            ->orderBy('public_id', 'desc')
            ->cursorPaginate($perPage)
            ->withQueryString();
    }
}
