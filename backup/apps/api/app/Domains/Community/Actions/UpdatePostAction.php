<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions;

use App\Domains\Community\Enums\ModerationState;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Exceptions\CommunityStateConflict;
use App\Domains\Community\Exceptions\CommunityVersionConflict;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Queries\FindPost;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class UpdatePostAction
{
    public function __construct(private FindPost $posts) {}

    public function execute(
        User $user,
        string $postPublicId,
        ?string $title,
        string $body,
        ?string $sharedResourcePublicId,
        int $expectedVersion,
    ): CommunityPost {
        try {
            $post = DB::transaction(function () use ($user, $postPublicId, $title, $body, $sharedResourcePublicId, $expectedVersion): CommunityPost {
                $post = $this->posts->execute($user, $postPublicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $post);

                if ($post->moderation_state !== ModerationState::Visible) {
                    throw new CommunityStateConflict('This post can no longer be edited.');
                }

                $sharedResourceId = $this->resolveSharedResource($user, $sharedResourcePublicId);
                $desired = ['title' => $title, 'body' => $body, 'shared_resource_id' => $sharedResourceId];
                $canonical = [
                    'title' => $post->title,
                    'body' => $post->body,
                    'shared_resource_id' => $post->shared_resource_id === null ? null : (int) $post->shared_resource_id,
                ];

                if ($canonical === $desired) {
                    return $post;
                }

                if ($post->version !== $expectedVersion) {
                    throw new CommunityVersionConflict;
                }

                $post->forceFill([...$desired, 'version' => $post->version + 1])->save();

                return $post;
            }, 3);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'post.update');
        }

        return $this->posts->loadForResponse($post);
    }

    private function resolveSharedResource(User $user, ?string $sharedResourcePublicId): ?int
    {
        if ($sharedResourcePublicId === null) {
            return null;
        }

        $resource = Resource::query()
            ->where('user_id', $user->getKey())
            ->where('public_id', $sharedResourcePublicId)
            ->where('kind', ResourceKind::Link->value)
            ->first();

        if (! $resource instanceof Resource) {
            throw ValidationException::withMessages([
                'shared_resource_id' => 'Only your own link resources can be shared to a community.',
            ]);
        }

        return (int) $resource->getKey();
    }
}
