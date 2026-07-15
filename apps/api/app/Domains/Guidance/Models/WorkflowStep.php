<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Models;

use App\Domains\Guidance\Enums\WorkflowDestinationAction;
use App\Domains\Tools\Models\Tool;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workflow_recipe_id
 * @property int $step_number
 * @property string $title
 * @property string $instruction
 * @property int|null $tool_id
 * @property int|null $prompt_template_id
 * @property WorkflowDestinationAction|null $destination_action
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read WorkflowRecipe $recipe
 * @property-read Tool|null $tool
 * @property-read PromptTemplate|null $promptTemplate
 */
final class WorkflowStep extends Model
{
    /** @use HasFactory<WorkflowStepFactory> */
    use HasFactory, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'workflow_recipe_id',
    ];

    protected $hidden = [
        'id',
        'workflow_recipe_id',
        'tool_id',
        'prompt_template_id',
    ];

    /** @return BelongsTo<WorkflowRecipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(WorkflowRecipe::class, 'workflow_recipe_id');
    }

    /** @return BelongsTo<Tool, $this> */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /** @return BelongsTo<PromptTemplate, $this> */
    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }

    protected static function newFactory(): WorkflowStepFactory
    {
        return WorkflowStepFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'destination_action' => WorkflowDestinationAction::class,
        ];
    }
}
