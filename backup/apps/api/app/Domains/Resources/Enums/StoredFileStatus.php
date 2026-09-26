<?php

declare(strict_types=1);

namespace App\Domains\Resources\Enums;

enum StoredFileStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case DeletionPending = 'deletion_pending';
}
