<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Support\PublishedPromptVisibility;
use App\Domains\Users\Models\User;

final class PromptTemplatePolicy
{
    public function view(User $user, PromptTemplate $prompt): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && PublishedPromptVisibility::allows($prompt);
    }
}
