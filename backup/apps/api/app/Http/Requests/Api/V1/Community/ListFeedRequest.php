<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Community;

final class ListFeedRequest extends CursorListRequest
{
    protected function cursorSort(): string
    {
        return '-created_at';
    }
}
