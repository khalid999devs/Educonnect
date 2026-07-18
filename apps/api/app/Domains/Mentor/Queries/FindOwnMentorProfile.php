<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Queries;

use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Users\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindOwnMentorProfile
{
    public function execute(User $user, bool $lockForUpdate = false): MentorProfile
    {
        $query = MentorProfile::query()->with('user')->where('user_id', $user->getKey());

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $profile = $query->first();

        if (! $profile instanceof MentorProfile) {
            throw new NotFoundHttpException;
        }

        return $profile;
    }

    public function exists(User $user): bool
    {
        return MentorProfile::query()->where('user_id', $user->getKey())->exists();
    }
}
