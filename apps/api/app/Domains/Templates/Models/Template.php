<?php

declare(strict_types=1);

namespace App\Domains\Templates\Models;

use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Templates\Enums\TemplateBadge;
use App\Domains\Tools\Models\ToolCategory;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $public_id
 * @property int $tool_category_id
 * @property string $title
 * @property string|null $summary
 * @property string|null $integrity_note
 * @property string|null $provenance
 * @property TemplateBadge $badge
 * @property GuidanceReviewState $state
 * @property CarbonImmutable|null $last_reviewed_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $archived_at
 * @property int $version
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read ToolCategory $category
 * @property-read Collection<int, TemplateVersion> $versions
 * @property-read TemplateVersion|null $latestVersion
 * @property-read Collection<int, UserTemplatePreference> $preferences
 * @property-read Collection<int, UserTemplateCopy> $copies
 */
final class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
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

    /** @return HasMany<TemplateVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->orderBy('version_number');
    }

    /** @return HasOne<TemplateVersion, $this> */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(TemplateVersion::class)->ofMany('version_number', 'max');
    }

    /** @return HasMany<UserTemplatePreference, $this> */
    public function preferences(): HasMany
    {
        return $this->hasMany(UserTemplatePreference::class);
    }

    /** @return HasMany<UserTemplateCopy, $this> */
    public function copies(): HasMany
    {
        return $this->hasMany(UserTemplateCopy::class);
    }

    protected static function newFactory(): TemplateFactory
    {
        return TemplateFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'badge' => TemplateBadge::class,
            'state' => GuidanceReviewState::class,
            'last_reviewed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'version' => 'integer',
            'template_title_asc' => 'string',
            'template_reviewed_at_desc' => 'immutable_datetime',
        ];
    }
}
