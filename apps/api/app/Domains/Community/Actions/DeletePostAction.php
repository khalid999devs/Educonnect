<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions;

use App\Domains\Community\Enums\ModerationState;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Exceptions\CommunityVersionConflict;
use App\Domains\Community\Queries\FindPost;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeletePostAction
{
    public function __construct(private FindPost $posts) {}

    public function execute(User $user, string $postPublicId, int $expectedVersion): void
    {
        try {
            DB::transaction(function () use ($user, $postPublicId, $expectedVersion): void {
                $post = $this->posts->execute($user, $postPublicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('delete', $post);

                if ($post->moderation_state === ModerationState::RemovedByAuthor) {
                    return;
                }

                if ($post->version !== $expectedVersion) {
                    throw new CommunityVersionConflict;
                }

                $post->forceFill([
                    'moderation_state' => ModerationState::RemovedByAuthor->value,
                    'version' => $post->version + 1,
                ])->save();
            }, 3);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'post.delete');
        }
    }
}
