<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions;

use App\Domains\Community\Enums\ModerationState;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Exceptions\CommunityVersionConflict;
use App\Domains\Community\Queries\FindComment;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteCommentAction
{
    public function __construct(private FindComment $comments) {}

    public function execute(User $user, string $commentPublicId, int $expectedVersion): void
    {
        try {
            DB::transaction(function () use ($user, $commentPublicId, $expectedVersion): void {
                $comment = $this->comments->execute($user, $commentPublicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('delete', $comment);

                if ($comment->moderation_state === ModerationState::RemovedByAuthor) {
                    return;
                }

                if ($comment->version !== $expectedVersion) {
                    throw new CommunityVersionConflict;
                }

                $comment->forceFill([
                    'moderation_state' => ModerationState::RemovedByAuthor->value,
                    'version' => $comment->version + 1,
                ])->save();
            }, 3);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'comment.delete');
        }
    }
}
