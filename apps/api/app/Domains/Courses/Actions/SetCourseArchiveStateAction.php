<?php

declare(strict_types=1);

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Exceptions\AcademicPersistenceFailure;
use App\Domains\Courses\Exceptions\AcademicVersionConflict;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SetCourseArchiveStateAction
{
    public function __construct(private FindOwnedCourse $courses) {}

    public function execute(User $user, string $publicId, bool $archived, int $expectedVersion): Course
    {
        $operation = $archived ? 'course.archive' : 'course.restore';

        try {
            return DB::transaction(function () use ($user, $publicId, $archived, $expectedVersion): Course {
                $course = $this->courses->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $course);
                $alreadyDesired = $archived ? $course->archived_at !== null : $course->archived_at === null;

                if ($alreadyDesired) {
                    return $course;
                }

                if ($course->version !== $expectedVersion) {
                    throw new AcademicVersionConflict;
                }

                $course->forceFill([
                    'archived_at' => $archived ? now() : null,
                    'version' => $course->version + 1,
                ])->save();

                return $course->refresh()->load('academicTerm');
            }, 3);
        } catch (QueryException $exception) {
            throw AcademicPersistenceFailure::fromQueryException($exception, $operation);
        }
    }
}
