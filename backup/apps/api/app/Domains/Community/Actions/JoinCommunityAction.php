<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions;

use App\Domains\Community\Enums\MembershipRole;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Models\Community;
use App\Domains\Community\Queries\FindCommunity;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class JoinCommunityAction
{
    public function __construct(private FindCommunity $communities) {}

    public function execute(User $user, string $communityPublicId): Community
    {
        try {
            return DB::transaction(function () use ($user, $communityPublicId): Community {
                $community = $this->communities->execute($user, $communityPublicId);

                DB::table('community_memberships')->insertOrIgnore([
                    'community_id' => $community->getKey(),
                    'user_id' => $user->getKey(),
                    'role' => MembershipRole::Member->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->communities->hydrateMembership($user, $community);

                return $community;
            }, 3);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'community.membership.join');
        }
    }
}
