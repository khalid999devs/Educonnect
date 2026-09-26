<?php

declare(strict_types=1);

namespace App\Domains\Tools\Models;

use App\Domains\Tools\Enums\ToolReviewState;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $tool_category_id
 * @property string $name
 * @property string|null $purpose
 * @property string|null $selection_reason
 * @property list<string>|null $use_cases
 * @property string|null $usage_guidance
 * @property string|null $limitations
 * @property string|null $cost_note
 * @property string|null $privacy_note
 * @property string|null $external_url
 * @property string|null $provenance
 * @property ToolReviewState $state
 * @property CarbonImmutable|null $last_reviewed_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $archived_at
 * @property int $version
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read ToolCategory $category
 * @property-read Collection<int, UserToolPreference> $preferences
 */
final class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'public_id',
        'state',
        'last_reviewed_at',
        'published_at',
        'archived_at',
        'version',
    ];

    protected $hidden = [
        'id',
        'tool_category_id',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<ToolCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }

    /** @return HasMany<UserToolPreference, $this> */
    public function preferences(): HasMany
    {
        return $this->hasMany(UserToolPreference::class);
    }

    protected static function newFactory(): ToolFactory
    {
        return ToolFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'use_cases' => 'array',
            'state' => ToolReviewState::class,
            'last_reviewed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'version' => 'integer',
            'tool_name_asc' => 'string',
            'tool_reviewed_at_desc' => 'immutable_datetime',
        ];
    }
}
