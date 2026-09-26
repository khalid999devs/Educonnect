<?php

declare(strict_types=1);

namespace App\Domains\Community\Models;

use App\Support\StoresUtcDateTimes;
use Database\Factories\CommunityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $slug
 * @property string $name
 * @property string $summary
 * @property string|null $description
 * @property string|null $topic
 * @property string $visibility
 * @property bool $is_seeded
 * @property int $version
 */
final class Community extends Model
{
    /** @use HasFactory<CommunityFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = ['id', 'public_id', 'version'];

    protected $hidden = ['id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return HasMany<CommunityMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(CommunityMembership::class);
    }

    /** @return HasMany<CommunityPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    protected static function newFactory(): CommunityFactory
    {
        return CommunityFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_seeded' => 'boolean',
            'version' => 'integer',
            'cursor_created_at_desc' => 'immutable_datetime',
        ];
    }
}
