<?php

declare(strict_types=1);

namespace App\Domains\Community\Models;

use App\Domains\Authorization\Contracts\ModerationTarget;
use App\Domains\Community\Enums\MembershipRole;
use App\Domains\Community\Enums\ModerationState;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Database\Factories\CommunityCommentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $post_id
 * @property int $author_id
 * @property string $body
 * @property ModerationState $moderation_state
 * @property int $version
 */
final class CommunityComment extends Model implements ModerationTarget
{
    /** @use HasFactory<CommunityCommentFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = ['id', 'public_id', 'post_id', 'author_id', 'version'];

    protected $hidden = ['id', 'post_id', 'author_id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<CommunityPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isInModerationScopeFor(User $moderator): bool
    {
        return CommunityMembership::query()
            ->where('user_id', $moderator->getKey())
            ->where('role', MembershipRole::Moderator->value)
            ->whereIn(
                'community_id',
                CommunityPost::query()->whereKey($this->post_id)->select('community_id'),
            )
            ->exists();
    }

    protected static function newFactory(): CommunityCommentFactory
    {
        return CommunityCommentFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'moderation_state' => ModerationState::class,
            'version' => 'integer',
            'cursor_created_at_asc' => 'immutable_datetime',
        ];
    }
}
