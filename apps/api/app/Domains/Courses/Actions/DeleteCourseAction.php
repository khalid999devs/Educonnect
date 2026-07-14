<?php

declare(strict_types=1);

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Exceptions\AcademicPersistenceFailure;
use App\Domains\Courses\Exceptions\AcademicStateConflict;
use App\Domains\Courses\Exceptions\AcademicVersionConflict;
use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteCourseAction
{
    public function __construct(private FindOwnedCourse $courses) {}

    public function execute(User $user, string $publicId, int $expectedVersion): void
    {
        try {
            DB::transaction(function () use ($user, $publicId, $expectedVersion): void {
                $course = $this->courses->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('delete', $course);

                if ($course->version !== $expectedVersion) {
                    throw new AcademicVersionConflict;
                }

                if ($course->tasks()->exists() || $course->focusSessions()->exists()) {
                    throw new AcademicStateConflict;
                }

                $course->delete();
            }, 3);
        } catch (QueryException $exception) {
            throw AcademicPersistenceFailure::fromQueryException($exception, 'course.delete');
        }
    }
}
