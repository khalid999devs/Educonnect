<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\SecondBrain\Models\ResearchTopic;
use App\Domains\Users\Models\User;

final class ResearchTopicPolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, ResearchTopic $topic): bool
    {
        return $this->owns($user, $topic);
    }

    public function update(User $user, ResearchTopic $topic): bool
    {
        return $this->owns($user, $topic);
    }

    public function delete(User $user, ResearchTopic $topic): bool
    {
        return $this->owns($user, $topic);
    }

    private function owns(User $user, ResearchTopic $topic): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $topic->user_id;
    }
}
