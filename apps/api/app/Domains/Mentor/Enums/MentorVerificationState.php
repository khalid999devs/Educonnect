<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Enums;

enum MentorVerificationState: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
}
