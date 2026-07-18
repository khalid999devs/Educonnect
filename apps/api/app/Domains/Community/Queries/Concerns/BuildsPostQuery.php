<?php

declare(strict_types=1);

namespace App\Domains\Community\Queries\Concerns;

use App\Domains\Community\Enums\ModerationState;
use App\Domains\Community\Models\CommunityPost;
use Illuminate\Database\Eloquent\Builder;

trait BuildsPostQuery
{
    /** @return Builder<CommunityPost> */
    private function basePostQuery(): Builder
    {
        return CommunityPost::query()
            ->with(['community', 'author', 'sharedResource'])
            ->withCount(['comments as comment_count' => static function (Builder $query): void {
                $query->where('moderation_state', ModerationState::Visible->value);
            }]);
    }
}
