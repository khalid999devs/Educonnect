<?php

declare(strict_types=1);

namespace App\Domains\Community\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Users\Models\User;

final class CommunityPostPolicy
{
    public function update(User $user, CommunityPost $post): bool
    {
        return $this->authored($user, $post);
    }

    public function delete(User $user, CommunityPost $post): bool
    {
        return $this->authored($user, $post);
    }

    private function authored(User $user, CommunityPost $post): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $post->author_id;
    }
}
