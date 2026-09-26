<?php

declare(strict_types=1);

namespace App\Domains\Community\Queries;

use App\Domains\Community\Actions\Concerns\GuardsCommunityMembership;
use App\Domains\Community\Data\CommunityListResult;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Community\Support\AuthorMentorStatus;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lists the people who belong to a community the viewer has joined.
 *
 * Only membership rows are read. Display names and the verified-mentor flag are
 * hydrated with narrow table-name lookups so no user record, and therefore no
 * email address, is ever loaded into memory for this endpoint.
 */
final class ListCommunityMembers
{
    use GuardsCommunityMembership;

    public function __construct(private readonly FindCommunity $communities) {}

    /** @return CommunityListResult<CommunityMembership> */
    public function execute(User $user, string $communityPublicId, int $perPage): CommunityListResult
    {
        $community = $this->communities->execute($user, $communityPublicId);

        $this->assertMember($user, (int) $community->getKey());

        try {
            $paginator = CommunityMembership::query()
                ->select([
                    'community_memberships.*',
                    'community_memberships.created_at as cursor_created_at_desc',
                ])
                ->where('community_id', $community->getKey())
                ->orderBy('cursor_created_at_desc', 'desc')
                ->orderBy('public_id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();

            $this->hydrateNames($paginator->getCollection());
            $this->hydrateMentorFlags($paginator->getCollection());

            $total = DB::table('community_memberships')
                ->where('community_id', $community->getKey())
                ->count();

            return new CommunityListResult($paginator, ['total' => $total]);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'community.member.list');
        }
    }

    /** @param Collection<int, CommunityMembership> $memberships */
    private function hydrateNames(Collection $memberships): void
    {
        $userIds = $this->userIds($memberships);

        if ($userIds === []) {
            return;
        }

        /** @var array<int, string> $names */
        $names = DB::table('users')
            ->whereIn('id', $userIds)
            ->pluck('name', 'id')
            ->mapWithKeys(static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();

        foreach ($memberships as $membership) {
            $membership->setAttribute('member_name', $names[(int) $membership->user_id] ?? 'Unknown');
        }
    }

    /** @param Collection<int, CommunityMembership> $memberships */
    private function hydrateMentorFlags(Collection $memberships): void
    {
        $verified = AuthorMentorStatus::verifiedUserIds($this->userIds($memberships));

        foreach ($memberships as $membership) {
            $membership->setAttribute(
                'member_is_verified_mentor',
                in_array((int) $membership->user_id, $verified, true),
            );
        }
    }

    /**
     * @param  Collection<int, CommunityMembership>  $memberships
     * @return list<int>
     */
    private function userIds(Collection $memberships): array
    {
        return $memberships
            ->map(static fn (CommunityMembership $membership): int => (int) $membership->user_id)
            ->unique()
            ->values()
            ->all();
    }
}
