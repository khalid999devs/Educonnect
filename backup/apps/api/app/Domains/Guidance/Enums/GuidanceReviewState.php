<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Enums;

enum GuidanceReviewState: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Archived = 'archived';
}
