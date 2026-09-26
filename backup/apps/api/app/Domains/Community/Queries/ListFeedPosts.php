<?php

declare(strict_types=1);

namespace App\Domains\Community\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Community\Data\CommunityListResult;
use App\Domains\Community\Enums\ModerationState;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Queries\Concerns\BuildsPostQuery;
use App\Domains\Community\Support\AuthorMentorStatus;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ListFeedPosts
{
    use BuildsPostQuery;

    /** @return CommunityListResult<CommunityPost> */
    public function execute(User $user, int $perPage): CommunityListResult
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $memberCommunityIds = DB::table('community_memberships')
                ->where('user_id', $user->getKey())
                ->pluck('community_id');

            $paginator = $this->basePostQuery()
                ->select(['community_posts.*', 'community_posts.created_at as cursor_created_at_desc'])
                ->whereIn('community_id', $memberCommunityIds)
                ->where('moderation_state', ModerationState::Visible->value)
                ->orderBy('cursor_created_at_desc', 'desc')
                ->orderBy('public_id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();

            AuthorMentorStatus::hydrate($paginator->getCollection());

            return new CommunityListResult($paginator, ['communities' => $memberCommunityIds->count()]);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'feed.list');
        }
    }
}
