<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Models;

use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\PromptTemplateFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $tool_category_id
 * @property string $title
 * @property string|null $purpose
 * @property string|null $template_body
 * @property list<string>|null $placeholders
 * @property string|null $expected_output
 * @property string|null $integrity_note
 * @property string|null $provenance
 * @property GuidanceReviewState $state
 * @property CarbonImmutable|null $last_reviewed_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $archived_at
 * @property int $version
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read ToolCategory $category
 * @property-read Collection<int, Tool> $relatedTools
 * @property-read Collection<int, UserPromptPreference> $preferences
 * @property-read Collection<int, UserPromptCopy> $copies
 */
final class PromptTemplate extends Model
{
    /** @use HasFactory<PromptTemplateFactory> */
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

    /** @return BelongsToMany<Tool, $this> */
    public function relatedTools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'prompt_template_tool');
    }

    /** @return HasMany<UserPromptPreference, $this> */
    public function preferences(): HasMany
    {
        return $this->hasMany(UserPromptPreference::class);
    }

    /** @return HasMany<UserPromptCopy, $this> */
    public function copies(): HasMany
    {
        return $this->hasMany(UserPromptCopy::class);
    }

    protected static function newFactory(): PromptTemplateFactory
    {
        return PromptTemplateFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'state' => GuidanceReviewState::class,
            'last_reviewed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'version' => 'integer',
            'prompt_title_asc' => 'string',
            'prompt_reviewed_at_desc' => 'immutable_datetime',
        ];
    }
}
