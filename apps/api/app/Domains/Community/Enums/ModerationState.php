<?php

declare(strict_types=1);

namespace App\Domains\Community\Enums;

enum ModerationState: string
{
    case Visible = 'visible';
    case HiddenByModerator = 'hidden_by_moderator';
    case RemovedByAuthor = 'removed_by_author';
}
