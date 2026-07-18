<?php

declare(strict_types=1);

namespace App\Domains\Community\Queries;

use App\Domains\Community\Data\CommunityListResult;
use App\Domains\Community\Enums\ModerationState;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Queries\Concerns\BuildsPostQuery;
use App\Domains\Community\Support\AuthorMentorStatus;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ListCommunityPosts
{
    use BuildsPostQuery;

    public function __construct(private readonly FindCommunity $communities) {}

    /** @return CommunityListResult<CommunityPost> */
    public function execute(User $user, string $communityPublicId, int $perPage): CommunityListResult
    {
        $community = $this->communities->execute($user, $communityPublicId);

        try {
            $paginator = $this->basePostQuery()
                ->select(['community_posts.*', 'community_posts.created_at as cursor_created_at_desc'])
                ->where('community_id', $community->getKey())
                ->where('moderation_state', ModerationState::Visible->value)
                ->orderBy('cursor_created_at_desc', 'desc')
                ->orderBy('public_id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();

            AuthorMentorStatus::hydrate($paginator->getCollection());

            $total = DB::table('community_posts')
                ->where('community_id', $community->getKey())
                ->where('moderation_state', ModerationState::Visible->value)
                ->count();

            return new CommunityListResult($paginator, ['total' => $total]);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'post.list');
        }
    }

    public function community(User $user, string $communityPublicId): Community
    {
        return $this->communities->execute($user, $communityPublicId);
    }
}
