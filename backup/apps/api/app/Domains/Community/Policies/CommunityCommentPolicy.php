<?php

declare(strict_types=1);

namespace App\Domains\Community\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Community\Models\CommunityComment;
use App\Domains\Users\Models\User;

final class CommunityCommentPolicy
{
    public function delete(User $user, CommunityComment $comment): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $comment->author_id;
    }
}
