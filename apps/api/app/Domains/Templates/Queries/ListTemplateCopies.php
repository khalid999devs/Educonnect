<?php

declare(strict_types=1);

namespace App\Domains\Templates\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Templates\Enums\TemplateCopyDestination;
use App\Domains\Templates\Exceptions\TemplatePersistenceFailure;
use App\Domains\Templates\Models\UserTemplateCopy;
use App\Domains\Templates\Support\TemplateCopyCursorSort;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ListTemplateCopies
{
    /** @return CursorPaginator<int, UserTemplateCopy> */
    public function execute(
        User $user,
        ?TemplateCopyDestination $destination,
        ?string $course,
        bool $includeArchived,
        string $sort,
        int $perPage,
    ): CursorPaginator {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        if ($perPage < 1 || $perPage > 50) {
            throw new InvalidArgumentException('Template copy page size must be between 1 and 50.');
        }

        try {
            $sortDefinition = TemplateCopyCursorSort::for($sort);
            $query = UserTemplateCopy::query()
                ->select('user_template_copies.*')
                ->selectRaw($sortDefinition['expression'].' as '.$sortDefinition['cursor_column'])
                ->where('user_template_copies.user_id', $user->getKey())
                ->with(['template', 'templateVersion', 'course']);

            if ($destination instanceof TemplateCopyDestination) {
                $query->where('user_template_copies.destination', $destination->value);
            }

            if ($course !== null) {
                $query->whereHas(
                    'course',
                    static fn (Builder $courses): Builder => $courses->where('public_id', $course),
                );
            }

            if (! $includeArchived) {
                $query->whereNull('user_template_copies.archived_at');
            }

            return $query
                ->orderBy($sortDefinition['cursor_column'], $sortDefinition['direction'])
                ->orderBy('public_id', $sortDefinition['direction'])
                ->cursorPaginate($perPage)
                ->withQueryString();
        } catch (QueryException $exception) {
            throw TemplatePersistenceFailure::fromQueryException($exception, 'template.copy.list');
        }
    }
}
