<?php

declare(strict_types=1);

namespace App\Domains\Community\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Community\Models\CommunityComment;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindComment
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): CommunityComment
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        $query = CommunityComment::query()->where('public_id', $publicId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $comment = $query->first();

        if (! $comment instanceof CommunityComment) {
            throw new NotFoundHttpException;
        }

        return $comment;
    }
}
