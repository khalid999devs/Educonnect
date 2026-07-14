<?php

declare(strict_types=1);

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Exceptions\AcademicPersistenceFailure;
use App\Domains\Courses\Exceptions\AcademicVersionConflict;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Queries\FindOwnedAcademicTerm;
use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateCourseAction
{
    public function __construct(
        private FindOwnedCourse $courses,
        private FindOwnedAcademicTerm $terms,
    ) {}

    /** @param array{title: string, code: ?string, description: ?string, term_id: ?string} $data */
    public function execute(User $user, string $publicId, array $data, int $expectedVersion): Course
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $data, $expectedVersion): Course {
                $course = $this->courses->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $course);
                $term = $data['term_id'] === null
                    ? null
                    : $this->terms->execute($user, $data['term_id'], lockForUpdate: true);
                $desired = [
                    'title' => $data['title'],
                    'code' => $data['code'],
                    'description' => $data['description'],
                    'academic_term_id' => $term?->getKey(),
                ];

                if ($this->canonical($course) === $desired) {
                    return $course;
                }

                if ($course->version !== $expectedVersion) {
                    throw new AcademicVersionConflict;
                }

                $course->forceFill([
                    ...$desired,
                    'version' => $course->version + 1,
                ])->save();

                return $course->refresh()->load('academicTerm');
            }, 3);
        } catch (QueryException $exception) {
            throw AcademicPersistenceFailure::fromQueryException($exception, 'course.update');
        }
    }

    /** @return array{title: string, code: ?string, description: ?string, academic_term_id: mixed} */
    private function canonical(Course $course): array
    {
        return [
            'title' => $course->title,
            'code' => $course->code,
            'description' => $course->description,
            'academic_term_id' => $course->academic_term_id,
        ];
    }
}
