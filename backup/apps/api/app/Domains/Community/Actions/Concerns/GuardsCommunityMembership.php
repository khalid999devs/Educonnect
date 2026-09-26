<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions\Concerns;

use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

trait GuardsCommunityMembership
{
    private function assertMember(User $user, int $communityId): void
    {
        $isMember = DB::table('community_memberships')
            ->where('community_id', $communityId)
            ->where('user_id', $user->getKey())
            ->exists();

        if (! $isMember) {
            throw new AuthorizationException('You must join this community before taking part.');
        }
    }
}
