<?php

declare(strict_types=1);

namespace App\Domains\Templates\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Support\PublishedTemplateVisibility;
use App\Domains\Users\Models\User;

final class TemplatePolicy
{
    public function view(User $user, Template $template): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && PublishedTemplateVisibility::allows($template);
    }
}
