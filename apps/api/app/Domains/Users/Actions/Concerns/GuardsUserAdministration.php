<?php

declare(strict_types=1);

namespace App\Domains\Users\Actions\Concerns;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

trait GuardsUserAdministration
{
    /**
     * A protected-role holder (admin/super admin) may only be administered by an
     * actor who can manage protected roles. This keeps an ordinary admin from
     * suspending or reactivating a peer administrator or a super admin.
     */
    protected function assertActorMayAdminister(User $actor, User $target): void
    {
        if ($actor->getKey() === $target->getKey()) {
            throw new AuthorizationException('You cannot administer your own account.');
        }

        $targetHasProtectedRole = $target->roles()->where('is_protected', true)->exists();

        if ($targetHasProtectedRole && ! $actor->hasCapability(CapabilityKey::ProtectedRolesManage)) {
            throw new AuthorizationException('You cannot administer a protected administrator account.');
        }
    }
}
