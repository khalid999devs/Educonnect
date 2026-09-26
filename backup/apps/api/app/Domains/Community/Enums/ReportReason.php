<?php

declare(strict_types=1);

namespace App\Domains\Community\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case OffTopic = 'off_topic';
    case Safety = 'safety';
    case Other = 'other';
}
