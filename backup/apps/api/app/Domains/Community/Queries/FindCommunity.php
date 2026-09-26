<?php

declare(strict_types=1);

namespace App\Domains\Community\Queries;

use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Users\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindCommunity
{
    public function execute(User $user, string $publicId, bool $includeArchived = false): Community
    {
        $query = Community::query()->where('public_id', $publicId);

        if (! $includeArchived) {
            $query->where('visibility', 'published');
        }

        $community = $query->first();

        if (! $community instanceof Community) {
            throw new NotFoundHttpException;
        }

        $this->hydrateMembership($user, $community);

        return $community;
    }

    public function hydrateMembership(User $user, Community $community): void
    {
        $membership = CommunityMembership::query()
            ->where('community_id', $community->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        $community->setAttribute('viewer_is_member', $membership !== null);
        $community->setAttribute(
            'viewer_membership_role',
            $membership?->getRawOriginal('role'),
        );
    }
}
