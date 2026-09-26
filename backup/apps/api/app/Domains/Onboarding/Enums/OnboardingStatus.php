<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Enums;

enum OnboardingStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
