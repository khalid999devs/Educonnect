<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Enums;

enum MentorRequestStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Completed = 'completed';
}
