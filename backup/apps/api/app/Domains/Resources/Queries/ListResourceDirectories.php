<?php

declare(strict_types=1);

namespace App\Domains\Resources\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Models\Course;
use App\Domains\Resources\Data\ResourceDirectory;
use App\Domains\Resources\Exceptions\ResourcePersistenceFailure;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The resources library directory listing: every active course, plus archived
 * courses that still hold resources, plus the unfiled bucket. This is a small
 * fixed set bounded by the owner's course roster, so it is deliberately not
 * cursor-paginated; it is hard-capped instead.
 */
final readonly class ListResourceDirectories
{
    private const MAX_COURSE_DIRECTORIES = 200;

    /** @return list<ResourceDirectory> */
    public function execute(User $user): array
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);
        $ownsTransaction = DB::transactionLevel() === 0;

        try {
            return DB::transaction(function () use ($user, $ownsTransaction): array {
                if ($ownsTransaction) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }

                return $this->buildDirectories($user);
            }, 3);
        } catch (QueryException $exception) {
            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.directories');
        }
    }

    /** @return list<ResourceDirectory> */
    private function buildDirectories(User $user): array
    {
        $unfiledCount = 0;
        /** @var array<int, int> $countsByCourseId */
        $countsByCourseId = [];

        $rows = DB::table('resources')
            ->where('user_id', $user->getKey())
            ->selectRaw('course_id, COUNT(*) AS total')
            ->groupBy('course_id')
            ->get();

        foreach ($rows as $row) {
            $total = (int) $row->total;

            if ($row->course_id === null) {
                $unfiledCount = $total;

                continue;
            }

            $countsByCourseId[(int) $row->course_id] = $total;
        }

        $courseIdsWithResources = array_keys($countsByCourseId);
        $courses = Course::query()
            ->where('user_id', $user->getKey())
            ->where(static function (Builder $scope) use ($courseIdsWithResources): void {
                $scope->whereNull('archived_at');

                if ($courseIdsWithResources !== []) {
                    $scope->orWhereIn('id', $courseIdsWithResources);
                }
            })
            ->orderByRaw('(archived_at IS NOT NULL)')
            ->orderBy('title')
            ->orderBy('public_id')
            ->limit(self::MAX_COURSE_DIRECTORIES)
            ->get();

        $directories = [];

        foreach ($courses as $course) {
            $directories[] = ResourceDirectory::forCourse(
                $course,
                $countsByCourseId[(int) $course->getKey()] ?? 0,
            );
        }

        $directories[] = ResourceDirectory::unfiled($unfiledCount);

        return $directories;
    }
}
