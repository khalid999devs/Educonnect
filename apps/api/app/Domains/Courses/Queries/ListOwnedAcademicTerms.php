<?php

declare(strict_types=1);

namespace App\Domains\Courses\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Exceptions\AcademicPersistenceFailure;
use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Support\AcademicCursorSort;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;

final class ListOwnedAcademicTerms
{
    /** @return CursorPaginator<int, AcademicTerm> */
    public function execute(
        User $user,
        ?string $search,
        string $sort,
        int $perPage,
    ): CursorPaginator {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $sortDefinition = AcademicCursorSort::resolve($sort);
            $query = AcademicTerm::query()
                ->select([
                    'academic_terms.*',
                    $sortDefinition['column'].' as '.$sortDefinition['cursor_column'],
                ])
                ->where('user_id', $user->getKey())
                ->withCount([
                    'courses as active_courses_count' => static fn (Builder $query): Builder => $query
                        ->whereNull('archived_at'),
                    'courses as archived_courses_count' => static fn (Builder $query): Builder => $query
                        ->whereNotNull('archived_at'),
                ]);

            if ($search !== null) {
                $query->whereRaw("LOWER(label) LIKE ? ESCAPE '\\'", [$this->prefixPattern($search)]);
            }

            return $query
                ->orderBy($sortDefinition['cursor_column'], $sortDefinition['direction'])
                ->orderBy('public_id', $sortDefinition['direction'])
                ->cursorPaginate($perPage)
                ->withQueryString();
        } catch (QueryException $exception) {
            throw AcademicPersistenceFailure::fromQueryException($exception, 'term.list');
        }
    }

    private function prefixPattern(string $search): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
    }
}
