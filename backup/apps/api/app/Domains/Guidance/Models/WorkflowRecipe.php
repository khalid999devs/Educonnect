<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Models;

use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Tools\Models\ToolCategory;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\WorkflowRecipeFactory;
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
 * @property string $title
 * @property string|null $goal
 * @property string|null $expected_outcome
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
 * @property-read Collection<int, WorkflowStep> $steps
 * @property-read Collection<int, UserWorkflowPreference> $preferences
 */
final class WorkflowRecipe extends Model
{
    /** @use HasFactory<WorkflowRecipeFactory> */
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

    /** @return HasMany<WorkflowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_number');
    }

    /** @return HasMany<UserWorkflowPreference, $this> */
    public function preferences(): HasMany
    {
        return $this->hasMany(UserWorkflowPreference::class);
    }

    protected static function newFactory(): WorkflowRecipeFactory
    {
        return WorkflowRecipeFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => GuidanceReviewState::class,
            'last_reviewed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'version' => 'integer',
            'workflow_title_asc' => 'string',
            'workflow_reviewed_at_desc' => 'immutable_datetime',
        ];
    }
}
