<?php

declare(strict_types=1);

namespace App\Domains\Community\Queries;

use App\Domains\Community\Data\CommunityListResult;
use App\Domains\Community\Enums\ModerationState;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Models\CommunityComment;
use App\Domains\Community\Support\AuthorMentorStatus;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ListPostComments
{
    public function __construct(private readonly FindPost $posts) {}

    /** @return CommunityListResult<CommunityComment> */
    public function execute(User $user, string $postPublicId, int $perPage): CommunityListResult
    {
        $post = $this->posts->execute($user, $postPublicId);

        try {
            $paginator = CommunityComment::query()
                ->with('author')
                ->select(['community_comments.*', 'community_comments.created_at as cursor_created_at_asc'])
                ->where('post_id', $post->getKey())
                ->where('moderation_state', ModerationState::Visible->value)
                ->orderBy('cursor_created_at_asc', 'asc')
                ->orderBy('public_id', 'asc')
                ->cursorPaginate($perPage)
                ->withQueryString();

            AuthorMentorStatus::hydrate($paginator->getCollection());

            $total = DB::table('community_comments')
                ->where('post_id', $post->getKey())
                ->where('moderation_state', ModerationState::Visible->value)
                ->count();

            return new CommunityListResult($paginator, ['total' => $total]);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'comment.list');
        }
    }
}
