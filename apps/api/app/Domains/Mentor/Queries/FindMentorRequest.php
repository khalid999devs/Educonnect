<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Queries;

use App\Domains\Mentor\Models\MentorRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindMentorRequest
{
    public function execute(string $publicId, bool $lockForUpdate = false): MentorRequest
    {
        $query = MentorRequest::query()
            ->with(['mentorProfile.user', 'requester'])
            ->where('public_id', $publicId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $request = $query->first();

        if (! $request instanceof MentorRequest) {
            throw new NotFoundHttpException;
        }

        return $request;
    }
}
