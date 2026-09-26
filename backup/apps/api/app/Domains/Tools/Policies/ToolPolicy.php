<?php

declare(strict_types=1);

namespace App\Domains\Tools\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Support\PublishedToolVisibility;
use App\Domains\Users\Models\User;

final class ToolPolicy
{
    public function view(User $user, Tool $tool): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && PublishedToolVisibility::allows($tool);
    }
}
