<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Tools\Models\ToolCategory;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The curator's view of a workflow recipe: content, ordered steps, and lifecycle
 * state in any state (no viewer preference state).
 *
 * @mixin WorkflowRecipe
 */
final class AdminWorkflowResource extends JsonResource
{
    use FormatsApiTimestamps;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $category = $this->whenLoaded('category');

        return [
            'id' => (string) $this->public_id,
            'title' => (string) $this->title,
            'category' => $category instanceof ToolCategory ? [
                'slug' => (string) $category->slug,
                'name' => (string) $category->name,
            ] : null,
            'goal' => (string) $this->goal,
            'expected_outcome' => (string) $this->expected_outcome,
            'integrity_note' => (string) $this->integrity_note,
            'provenance' => (string) $this->provenance,
            'steps' => $this->stepList(),
            'state' => $this->state->value,
            'last_reviewed_at' => $this->timestamp($this->last_reviewed_at),
            'published_at' => $this->timestamp($this->published_at),
            'archived_at' => $this->timestamp($this->archived_at),
            'version' => $this->version,
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
            'updated_at' => $this->timestamp($this->getAttribute('updated_at')),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function stepList(): array
    {
        if (! $this->relationLoaded('steps')) {
            return [];
        }

        return $this->steps
            ->map(static fn (WorkflowStep $step): array => [
                'number' => (int) $step->step_number,
                'title' => (string) $step->title,
                'instruction' => (string) $step->instruction,
                'destination_action' => $step->destination_action?->value,
            ])
            ->values()
            ->all();
    }
}
