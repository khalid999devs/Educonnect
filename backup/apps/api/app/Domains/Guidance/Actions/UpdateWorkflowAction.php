<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Content\Exceptions\ContentStateConflict;
use App\Domains\Content\Exceptions\ContentVersionConflict;
use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateWorkflowAction
{
    /**
     * Edit a draft workflow's content and steps. Only a draft is editable.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, WorkflowRecipe $workflow, array $data, int $expectedVersion): WorkflowRecipe
    {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Content curation is not allowed.');
        }

        $category = ToolCategory::query()->where('slug', $data['category_slug'])->firstOrFail();

        return DB::transaction(function () use ($workflow, $data, $expectedVersion, $category): WorkflowRecipe {
            $locked = WorkflowRecipe::query()->lockForUpdate()->findOrFail($workflow->getKey());

            if ($locked->version !== $expectedVersion) {
                throw new ContentVersionConflict;
            }

            if ($locked->state !== GuidanceReviewState::Draft) {
                throw new ContentStateConflict('Only draft content can be edited; return it to draft first.');
            }

            $locked->forceFill([
                'title' => $data['title'],
                'goal' => $data['goal'],
                'expected_outcome' => $data['expected_outcome'],
                'integrity_note' => $data['integrity_note'],
                'provenance' => $data['provenance'],
                'tool_category_id' => $category->getKey(),
                'version' => $locked->version + 1,
            ])->save();

            $locked->steps()->delete();

            foreach (array_values($data['steps']) as $index => $step) {
                $model = new WorkflowStep;
                $model->forceFill([
                    'workflow_recipe_id' => $locked->getKey(),
                    'step_number' => $index + 1,
                    'title' => $step['title'],
                    'instruction' => $step['instruction'],
                    'tool_id' => null,
                    'prompt_template_id' => null,
                    'template_id' => null,
                    'destination_action' => $step['destination_action'] ?? null,
                ])->save();
            }

            return $locked->load(['category', 'steps']);
        }, 3);
    }
}
