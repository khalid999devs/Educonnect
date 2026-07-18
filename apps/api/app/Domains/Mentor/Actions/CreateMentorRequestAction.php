<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Mentor\Enums\MentorRequestStatus;
use App\Domains\Mentor\Exceptions\MentorPersistenceFailure;
use App\Domains\Mentor\Exceptions\MentorStateConflict;
use App\Domains\Mentor\Models\MentorRequest;
use App\Domains\Mentor\Queries\FindMentorProfile;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CreateMentorRequestAction
{
    public function __construct(private FindMentorProfile $profiles) {}

    public function execute(
        User $user,
        string $mentorProfilePublicId,
        string $subject,
        string $message,
        ?string $contextCoursePublicId,
    ): MentorRequest {
        try {
            return DB::transaction(function () use ($user, $mentorProfilePublicId, $subject, $message, $contextCoursePublicId): MentorRequest {
                $profile = $this->profiles->execute($mentorProfilePublicId);
                Gate::forUser($user)->authorize('create', [MentorRequest::class, $profile]);

                if (! $profile->is_accepting_requests) {
                    throw new MentorStateConflict('This mentor is not currently accepting requests.');
                }

                $contextCourseId = $this->resolveContextCourse($user, $contextCoursePublicId);

                $request = new MentorRequest;
                $request->forceFill([
                    'requester_id' => $user->getKey(),
                    'mentor_profile_id' => $profile->getKey(),
                    'subject' => $subject,
                    'message' => $message,
                    'context_course_id' => $contextCourseId,
                    'status' => MentorRequestStatus::Open->value,
                    'version' => 1,
                ])->save();

                return $request->load(['mentorProfile.user']);
            }, 3);
        } catch (QueryException $exception) {
            throw MentorPersistenceFailure::fromQueryException($exception, 'mentor.request.create');
        }
    }

    private function resolveContextCourse(User $user, ?string $contextCoursePublicId): ?int
    {
        if ($contextCoursePublicId === null) {
            return null;
        }

        $course = Course::query()
            ->where('user_id', $user->getKey())
            ->where('public_id', $contextCoursePublicId)
            ->first();

        if (! $course instanceof Course) {
            throw ValidationException::withMessages([
                'context_course_id' => 'The selected course could not be found.',
            ]);
        }

        return (int) $course->getKey();
    }
}
