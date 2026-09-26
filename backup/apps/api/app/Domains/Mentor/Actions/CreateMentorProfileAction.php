<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Actions;

use App\Domains\Mentor\Enums\MentorVerificationState;
use App\Domains\Mentor\Exceptions\MentorPersistenceFailure;
use App\Domains\Mentor\Exceptions\MentorStateConflict;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Mentor\Queries\FindOwnMentorProfile;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class CreateMentorProfileAction
{
    public function __construct(private FindOwnMentorProfile $profiles) {}

    /**
     * @param  array{headline: string, bio: string, expertise: list<string>, availability_note: ?string, is_accepting_requests: bool}  $data
     */
    public function execute(User $user, array $data): MentorProfile
    {
        Gate::forUser($user)->authorize('create', MentorProfile::class);

        try {
            return DB::transaction(function () use ($user, $data): MentorProfile {
                if ($this->profiles->exists($user)) {
                    throw new MentorStateConflict('You already have a mentor profile.');
                }

                $profile = new MentorProfile;
                $profile->forceFill([
                    'user_id' => $user->getKey(),
                    'headline' => $data['headline'],
                    'bio' => $data['bio'],
                    'expertise' => $data['expertise'],
                    'availability_note' => $data['availability_note'],
                    'verification_state' => MentorVerificationState::Unverified->value,
                    'is_accepting_requests' => $data['is_accepting_requests'],
                    'version' => 1,
                ])->save();

                return $profile->load('user');
            }, 3);
        } catch (QueryException $exception) {
            throw MentorPersistenceFailure::fromQueryException($exception, 'mentor.profile.create');
        }
    }
}
