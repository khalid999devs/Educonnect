<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Enums;

enum OnboardingStepState: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Skipped = 'skipped';

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
