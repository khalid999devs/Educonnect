<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Queries;

use App\Domains\Mentor\Models\MentorProfile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindMentorProfile
{
    public function execute(string $publicId, bool $lockForUpdate = false): MentorProfile
    {
        $query = MentorProfile::query()->with('user')->where('public_id', $publicId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $profile = $query->first();

        if (! $profile instanceof MentorProfile) {
            throw new NotFoundHttpException;
        }

        return $profile;
    }
}
