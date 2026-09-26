<?php

declare(strict_types=1);

namespace App\Domains\Resources\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Exceptions\ResourcePersistenceFailure;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Support\ResourceCursorSort;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class ListOwnedResources
{
    public function __construct(private FindOwnedCourse $courses) {}

    /** @return CursorPaginator<int, resource> */
    public function execute(
        User $user,
        ?string $search,
        ?ResourceKind $kind,
        ?string $coursePublicId,
        bool $unfiledOnly,
        ?string $topic,
        ?StoredFileStatus $fileStatus,
        string $sort,
        int $perPage,
    ): CursorPaginator {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        if ($perPage < 1 || $perPage > 50) {
            throw new InvalidArgumentException('Resource page size must be between 1 and 50.');
        }

        try {
            $course = $coursePublicId === null ? null : $this->courses->execute($user, $coursePublicId);
            $sortDefinition = ResourceCursorSort::for($sort);
            $query = Resource::query()
                ->select([
                    'resources.*',
                    $sortDefinition['column'].' as '.$sortDefinition['cursor_column'],
                ])
                ->where('user_id', $user->getKey())
                ->with(['course', 'storedFile']);

            if ($kind !== null) {
                $query->where('kind', $kind->value);
            }

            if ($unfiledOnly) {
                // The Unfiled directory: served by resources_owner_updated_cursor_idx.
                $query->whereNull('course_id');
            } elseif ($course !== null) {
                $query->where('course_id', $course->getKey());
            }

            if ($topic !== null) {
                $query->where('topic_label', $topic);
            }

            if ($fileStatus !== null) {
                $query->whereHas(
                    'storedFile',
                    static fn (Builder $storedFiles): Builder => $storedFiles->where('status', $fileStatus->value),
                );
            }

            if ($search !== null) {
                $query->whereRaw("LOWER(title) LIKE ? ESCAPE '\\'", [$this->prefixPattern($search)]);
            }

            return $query
                ->orderBy($sortDefinition['cursor_column'], $sortDefinition['direction'])
                ->orderBy('public_id', $sortDefinition['direction'])
                ->cursorPaginate($perPage)
                ->withQueryString();
        } catch (QueryException $exception) {
            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.list');
        }
    }

    private function prefixPattern(string $search): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
    }
}
