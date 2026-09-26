<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Actions;

use App\Domains\Mentor\Exceptions\MentorPersistenceFailure;
use App\Domains\Mentor\Exceptions\MentorVersionConflict;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Mentor\Queries\FindOwnMentorProfile;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateMentorProfileAction
{
    public function __construct(private FindOwnMentorProfile $profiles) {}

    /**
     * @param  array{headline: string, bio: string, expertise: list<string>, availability_note: ?string, is_accepting_requests: bool}  $data
     */
    public function execute(User $user, array $data, int $expectedVersion): MentorProfile
    {
        try {
            return DB::transaction(function () use ($user, $data, $expectedVersion): MentorProfile {
                $profile = $this->profiles->execute($user, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $profile);

                $desired = [
                    'headline' => $data['headline'],
                    'bio' => $data['bio'],
                    'expertise' => $data['expertise'],
                    'availability_note' => $data['availability_note'],
                    'is_accepting_requests' => $data['is_accepting_requests'],
                ];
                $canonical = [
                    'headline' => $profile->headline,
                    'bio' => $profile->bio,
                    'expertise' => $profile->expertise,
                    'availability_note' => $profile->availability_note,
                    'is_accepting_requests' => $profile->is_accepting_requests,
                ];

                if ($canonical === $desired) {
                    return $profile->load('user');
                }

                if ($profile->version !== $expectedVersion) {
                    throw new MentorVersionConflict;
                }

                $profile->forceFill([...$desired, 'version' => $profile->version + 1])->save();

                return $profile->refresh()->load('user');
            }, 3);
        } catch (QueryException $exception) {
            throw MentorPersistenceFailure::fromQueryException($exception, 'mentor.profile.update');
        }
    }
}
