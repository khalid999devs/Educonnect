<?php

declare(strict_types=1);

namespace App\Domains\Community\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Queries\Concerns\BuildsPostQuery;
use App\Domains\Community\Support\AuthorMentorStatus;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindPost
{
    use BuildsPostQuery;

    public function execute(User $user, string $publicId, bool $lockForUpdate = false): CommunityPost
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        if ($lockForUpdate) {
            $post = CommunityPost::query()
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->first();

            if (! $post instanceof CommunityPost) {
                throw new NotFoundHttpException;
            }

            return $post;
        }

        $post = $this->basePostQuery()->where('public_id', $publicId)->first();

        if (! $post instanceof CommunityPost) {
            throw new NotFoundHttpException;
        }

        AuthorMentorStatus::hydrate(collect([$post]));

        return $post;
    }

    public function loadForResponse(CommunityPost $post): CommunityPost
    {
        $fresh = $this->basePostQuery()->whereKey($post->getKey())->first();

        if (! $fresh instanceof CommunityPost) {
            throw new NotFoundHttpException;
        }

        AuthorMentorStatus::hydrate(collect([$fresh]));

        return $fresh;
    }
}
