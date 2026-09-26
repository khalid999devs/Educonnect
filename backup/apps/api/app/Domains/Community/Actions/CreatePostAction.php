<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions;

use App\Domains\Community\Actions\Concerns\GuardsCommunityMembership;
use App\Domains\Community\Enums\ModerationState;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Queries\FindCommunity;
use App\Domains\Community\Support\AuthorMentorStatus;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreatePostAction
{
    use GuardsCommunityMembership;

    public function __construct(private FindCommunity $communities) {}

    public function execute(
        User $user,
        string $communityPublicId,
        ?string $title,
        string $body,
        ?string $sharedResourcePublicId,
    ): CommunityPost {
        try {
            $post = DB::transaction(function () use ($user, $communityPublicId, $title, $body, $sharedResourcePublicId): CommunityPost {
                $community = $this->communities->execute($user, $communityPublicId);
                $this->assertMember($user, (int) $community->getKey());
                $sharedResourceId = $this->resolveSharedResource($user, $sharedResourcePublicId);

                $post = new CommunityPost;
                $post->forceFill([
                    'community_id' => $community->getKey(),
                    'author_id' => $user->getKey(),
                    'title' => $title,
                    'body' => $body,
                    'shared_resource_id' => $sharedResourceId,
                    'moderation_state' => ModerationState::Visible->value,
                    'version' => 1,
                ])->save();

                return $post->load(['community', 'author', 'sharedResource']);
            }, 3);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'post.create');
        }

        $post->setAttribute('comment_count', 0);
        AuthorMentorStatus::hydrate(collect([$post]));

        return $post;
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
