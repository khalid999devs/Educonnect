<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CreateWorkflowAction
{
    /**
     * Create a workflow recipe as a draft with ordered steps.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): WorkflowRecipe
    {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Content curation is not allowed.');
        }

        $category = ToolCategory::query()->where('slug', $data['category_slug'])->firstOrFail();

        return DB::transaction(function () use ($data, $category): WorkflowRecipe {
            $workflow = new WorkflowRecipe;
            $workflow->forceFill([
                'title' => $data['title'],
                'goal' => $data['goal'],
                'expected_outcome' => $data['expected_outcome'],
                'integrity_note' => $data['integrity_note'],
                'provenance' => $data['provenance'],
                'tool_category_id' => $category->getKey(),
                'state' => GuidanceReviewState::Draft->value,
                'version' => 1,
            ])->save();

            $this->replaceSteps($workflow, $data['steps']);

            return $workflow->load(['category', 'steps']);
        }, 3);
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    private function replaceSteps(WorkflowRecipe $workflow, array $steps): void
    {
        $workflow->steps()->delete();

        foreach ($steps as $index => $step) {
            $model = new WorkflowStep;
            $model->forceFill([
                'workflow_recipe_id' => $workflow->getKey(),
                'step_number' => $index + 1,
                'title' => $step['title'],
                'instruction' => $step['instruction'],
                'tool_id' => null,
                'prompt_template_id' => null,
                'template_id' => null,
                'destination_action' => $step['destination_action'] ?? null,
            ])->save();
        }
    }
}
