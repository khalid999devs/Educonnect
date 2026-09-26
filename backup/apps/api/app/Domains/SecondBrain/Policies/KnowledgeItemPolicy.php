<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Users\Models\User;

final class KnowledgeItemPolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, KnowledgeItem $item): bool
    {
        return $this->owns($user, $item);
    }

    public function update(User $user, KnowledgeItem $item): bool
    {
        return $this->owns($user, $item);
    }

    public function delete(User $user, KnowledgeItem $item): bool
    {
        return $this->owns($user, $item);
    }

    private function owns(User $user, KnowledgeItem $item): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $item->user_id;
    }
}
