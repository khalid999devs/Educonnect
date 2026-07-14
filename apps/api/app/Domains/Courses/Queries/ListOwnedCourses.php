<?php

declare(strict_types=1);

namespace App\Domains\Courses\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Data\CourseListResult;
use App\Domains\Courses\Exceptions\AcademicPersistenceFailure;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Support\AcademicCursorSort;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class ListOwnedCourses
{
    public function __construct(private FindOwnedAcademicTerm $terms) {}

    public function execute(
        User $user,
        ?string $search,
        string $status,
        ?string $termPublicId,
        string $sort,
        int $perPage,
    ): CourseListResult {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);
        $ownsTransaction = DB::transactionLevel() === 0;

        try {
            return DB::transaction(function () use (
                $user,
                $search,
                $status,
                $termPublicId,
                $sort,
                $perPage,
                $ownsTransaction,
            ): CourseListResult {
                if ($ownsTransaction) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }

                $term = $termPublicId === null ? null : $this->terms->execute($user, $termPublicId);
                $sortDefinition = AcademicCursorSort::resolve($sort);
                $query = Course::query()
                    ->select([
                        'courses.*',
                        $sortDefinition['column'].' as '.$sortDefinition['cursor_column'],
                    ])
                    ->where('user_id', $user->getKey())
                    ->with('academicTerm');

                if ($status === 'active') {
                    $query->whereNull('archived_at');
                } elseif ($status === 'archived') {
                    $query->whereNotNull('archived_at');
                }

                if ($term !== null) {
                    $query->where('academic_term_id', $term->getKey());
                }

                if ($search !== null) {
                    $pattern = $this->prefixPattern($search);
                    $query->where(static function ($query) use ($pattern): void {
                        $query->whereRaw("LOWER(title) LIKE ? ESCAPE '\\'", [$pattern])
                            ->orWhereRaw("LOWER(code) LIKE ? ESCAPE '\\'", [$pattern]);
                    });
                }

                $paginator = $query
                    ->orderBy($sortDefinition['cursor_column'], $sortDefinition['direction'])
                    ->orderBy('public_id', $sortDefinition['direction'])
                    ->cursorPaginate($perPage)
                    ->withQueryString();
                $counts = DB::table('courses')
                    ->where('user_id', $user->getKey())
                    ->selectRaw('COUNT(*) AS total')
                    ->selectRaw('COUNT(*) FILTER (WHERE archived_at IS NULL) AS active')
                    ->selectRaw('COUNT(*) FILTER (WHERE archived_at IS NOT NULL) AS archived')
                    ->first();

                return new CourseListResult($paginator, [
                    'total' => (int) $counts->total,
                    'active' => (int) $counts->active,
                    'archived' => (int) $counts->archived,
                ]);
            }, 3);
        } catch (QueryException $exception) {
            throw AcademicPersistenceFailure::fromQueryException($exception, 'course.list');
        }
    }

    private function prefixPattern(string $search): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
    }
}
