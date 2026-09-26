<?php

declare(strict_types=1);

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Exceptions\AcademicPersistenceFailure;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Queries\FindOwnedAcademicTerm;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class CreateCourseAction
{
    public function __construct(private FindOwnedAcademicTerm $terms) {}

    /** @param array{title: string, code: ?string, description: ?string, term_id: ?string} $data */
    public function execute(User $user, array $data): Course
    {
        Gate::forUser($user)->authorize('create', Course::class);

        try {
            return DB::transaction(function () use ($user, $data): Course {
                $term = $data['term_id'] === null
                    ? null
                    : $this->terms->execute($user, $data['term_id'], lockForUpdate: true);
                $course = new Course;
                $course->forceFill([
                    'user_id' => $user->getKey(),
                    'academic_term_id' => $term?->getKey(),
                    'title' => $data['title'],
                    'code' => $data['code'],
                    'description' => $data['description'],
                    'version' => 1,
                    'archived_at' => null,
                ])->save();

                return $course->load('academicTerm');
            }, 3);
        } catch (QueryException $exception) {
            throw AcademicPersistenceFailure::fromQueryException($exception, 'course.create');
        }
    }
}
