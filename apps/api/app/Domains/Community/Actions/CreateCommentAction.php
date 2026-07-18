<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions;

use App\Domains\Community\Actions\Concerns\GuardsCommunityMembership;
use App\Domains\Community\Enums\ModerationState;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Exceptions\CommunityStateConflict;
use App\Domains\Community\Models\CommunityComment;
use App\Domains\Community\Queries\FindPost;
use App\Domains\Community\Support\AuthorMentorStatus;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommentAction
{
    use GuardsCommunityMembership;

    public function __construct(private FindPost $posts) {}

    public function execute(User $user, string $postPublicId, string $body): CommunityComment
    {
        try {
            $comment = DB::transaction(function () use ($user, $postPublicId, $body): CommunityComment {
                $post = $this->posts->execute($user, $postPublicId, lockForUpdate: true);
                $this->assertMember($user, (int) $post->community_id);

                if ($post->moderation_state !== ModerationState::Visible) {
                    throw new CommunityStateConflict('This post is not accepting comments.');
                }

                $comment = new CommunityComment;
                $comment->forceFill([
                    'post_id' => $post->getKey(),
                    'author_id' => $user->getKey(),
                    'body' => $body,
                    'moderation_state' => ModerationState::Visible->value,
                    'version' => 1,
                ])->save();

                return $comment->load('author');
            }, 3);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'comment.create');
        }

        AuthorMentorStatus::hydrate(collect([$comment]));

        return $comment;
    }
}
