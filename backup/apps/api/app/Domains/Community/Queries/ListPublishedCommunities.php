<?php

declare(strict_types=1);

namespace App\Domains\Community\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Community\Data\CommunityListResult;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ListPublishedCommunities
{
    /** @return CommunityListResult<Community> */
    public function execute(User $user, ?string $search, int $perPage): CommunityListResult
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = Community::query()
                ->select(['communities.*', 'communities.created_at as cursor_created_at_desc'])
                ->where('visibility', 'published');

            if ($search !== null) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
                $query->where(static function ($matches) use ($like): void {
                    $matches->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(summary) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(topic, \'\')) LIKE ?', [$like]);
                });
            }

            $paginator = $query
                ->orderBy('cursor_created_at_desc', 'desc')
                ->orderBy('public_id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();

            $this->hydrateMembership($user, $paginator->getCollection());

            $total = DB::table('communities')->where('visibility', 'published')->count();

            return new CommunityListResult($paginator, ['total' => $total]);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'community.list');
        }
    }

    /** @param Collection<int, Community> $communities */
    private function hydrateMembership(User $user, $communities): void
    {
        $ids = $communities->map(static fn (Community $community): int => (int) $community->getKey())->all();

        $memberships = $ids === []
            ? collect()
            : CommunityMembership::query()
                ->where('user_id', $user->getKey())
                ->whereIn('community_id', $ids)
                ->get()
                ->keyBy('community_id');

        foreach ($communities as $community) {
            $membership = $memberships->get($community->getKey());
            $community->setAttribute('viewer_is_member', $membership !== null);
            $community->setAttribute('viewer_membership_role', $membership?->getRawOriginal('role'));
        }
    }
}
