<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Mentor\Models\MentorRequest;
use App\Domains\Users\Models\User;

final class MentorRequestPolicy
{
    /** The requester may create a help request against a mentor profile that is not their own. */
    public function create(User $user, MentorProfile $profile): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() !== (int) $profile->user_id;
    }

    /** The requester may withdraw; the mentor who owns the target profile may respond. */
    public function respond(User $user, MentorRequest $request): bool
    {
        return (int) $user->getKey() === (int) $request->mentorProfile->user_id;
    }

    public function withdraw(User $user, MentorRequest $request): bool
    {
        return (int) $user->getKey() === (int) $request->requester_id;
    }
}
