<?php

declare(strict_types=1);

namespace App\Domains\Resources\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Users\Models\User;

final class StoredFilePolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, StoredFile $storedFile): bool
    {
        return $this->owns($user, $storedFile);
    }

    public function update(User $user, StoredFile $storedFile): bool
    {
        return $this->owns($user, $storedFile);
    }

    public function delete(User $user, StoredFile $storedFile): bool
    {
        return $this->owns($user, $storedFile);
    }

    private function owns(User $user, StoredFile $storedFile): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $storedFile->user_id;
    }
}
