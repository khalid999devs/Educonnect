<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkflowStep> */
final class WorkflowStepFactory extends Factory
{
    protected $model = WorkflowStep::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'workflow_recipe_id' => WorkflowRecipe::factory(),
            'step_number' => 1,
            'title' => 'Collect the source material',
            'instruction' => 'Gather the course materials this goal depends on and record where each one came from.',
            'tool_id' => null,
            'prompt_template_id' => null,
            'template_id' => null,
            'destination_action' => null,
        ];
    }
}
