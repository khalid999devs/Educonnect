<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions;

use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Models\Community;
use App\Domains\Community\Queries\FindCommunity;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class LeaveCommunityAction
{
    public function __construct(private FindCommunity $communities) {}

    public function execute(User $user, string $communityPublicId): Community
    {
        try {
            return DB::transaction(function () use ($user, $communityPublicId): Community {
                $community = $this->communities->execute($user, $communityPublicId, includeArchived: true);

                DB::table('community_memberships')
                    ->where('community_id', $community->getKey())
                    ->where('user_id', $user->getKey())
                    ->delete();

                $this->communities->hydrateMembership($user, $community);

                return $community;
            }, 3);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'community.membership.leave');
        }
    }
}
