<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Support\PublishedWorkflowVisibility;
use App\Domains\Users\Models\User;

final class WorkflowRecipePolicy
{
    public function view(User $user, WorkflowRecipe $workflow): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && PublishedWorkflowVisibility::allows($workflow);
    }
}
