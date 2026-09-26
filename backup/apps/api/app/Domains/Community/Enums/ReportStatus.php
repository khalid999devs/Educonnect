<?php

declare(strict_types=1);

namespace App\Domains\Community\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case Reviewing = 'reviewing';
    case Actioned = 'actioned';
    case Dismissed = 'dismissed';
}
