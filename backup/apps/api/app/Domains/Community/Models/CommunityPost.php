<?php

declare(strict_types=1);

namespace App\Domains\Community\Models;

use App\Domains\Authorization\Contracts\ModerationTarget;
use App\Domains\Community\Enums\MembershipRole;
use App\Domains\Community\Enums\ModerationState;
use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Database\Factories\CommunityPostFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $community_id
 * @property int $author_id
 * @property string|null $title
 * @property string $body
 * @property int|null $shared_resource_id
 * @property ModerationState $moderation_state
 * @property int $version
 */
final class CommunityPost extends Model implements ModerationTarget
{
    /** @use HasFactory<CommunityPostFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = ['id', 'public_id', 'community_id', 'author_id', 'version'];

    protected $hidden = ['id', 'community_id', 'author_id', 'shared_resource_id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<\App\Domains\Resources\Models\Resource, $this> */
    public function sharedResource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'shared_resource_id');
    }

    /** @return HasMany<CommunityComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class, 'post_id');
    }

    public function isInModerationScopeFor(User $moderator): bool
    {
        return CommunityMembership::query()
            ->where('community_id', $this->community_id)
            ->where('user_id', $moderator->getKey())
            ->where('role', MembershipRole::Moderator->value)
            ->exists();
    }

    protected static function newFactory(): CommunityPostFactory
    {
        return CommunityPostFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'moderation_state' => ModerationState::class,
            'version' => 'integer',
            'cursor_created_at_desc' => 'immutable_datetime',
        ];
    }
}
