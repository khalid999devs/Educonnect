<?php

declare(strict_types=1);

namespace App\Domains\Courses\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Exceptions\AcademicPersistenceFailure;
use App\Domains\Courses\Models\Course;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindOwnedCourse
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): Course
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = Course::query()
                ->where('user_id', $user->getKey())
                ->where('public_id', $publicId)
                ->with('academicTerm');

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $course = $query->first();

            if (! $course instanceof Course) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $course);

            return $course;
        } catch (QueryException $exception) {
            throw AcademicPersistenceFailure::fromQueryException($exception, 'course.read');
        }
    }
}
