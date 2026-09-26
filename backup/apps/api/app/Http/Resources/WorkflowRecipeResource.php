<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Guidance\Support\PublishedPromptVisibility;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Support\PublishedTemplateVisibility;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Tools\Support\PublishedToolVisibility;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowRecipe */
final class WorkflowRecipeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $category = $this->relationLoaded('category') ? $this->getRelation('category') : null;
        $preference = $this->getAttribute('viewer_preference_state');
        $state = $preference instanceof GuidancePreferenceState
            ? $preference
            : (is_string($preference) ? GuidancePreferenceState::tryFrom($preference) : null);

        return [
            'id' => (string) $this->public_id,
            'title' => (string) $this->title,
            'category' => $category instanceof ToolCategory ? [
                'key' => (string) $category->slug,
                'name' => (string) $category->name,
            ] : null,
            'goal' => (string) $this->goal,
            'expected_outcome' => (string) $this->expected_outcome,
            'integrity_note' => (string) $this->integrity_note,
            'provenance' => (string) $this->provenance,
            'steps' => $this->stepSummaries(),
            'last_reviewed_at' => $this->timestamp($this->last_reviewed_at),
            'viewer_state' => [
                'saved' => $state === GuidancePreferenceState::Saved,
                'dismissed' => $state === GuidancePreferenceState::Dismissed,
            ],
        ];
    }

    /**
     * @return list<array{
     *     number: int,
     *     title: string,
     *     instruction: string,
     *     destination_action: string|null,
     *     tool: array{id: string, name: string, url: string}|null,
     *     prompt: array{id: string, title: string}|null,
     *     template: array{id: string, title: string}|null,
     * }>
     */
    private function stepSummaries(): array
    {
        if (! $this->relationLoaded('steps')) {
            return [];
        }

        $summaries = [];

        foreach ($this->getRelation('steps') as $step) {
            if (! $step instanceof WorkflowStep) {
                continue;
            }

            $tool = $step->relationLoaded('tool') ? $step->getRelation('tool') : null;
            $prompt = $step->relationLoaded('promptTemplate') ? $step->getRelation('promptTemplate') : null;
            $template = $step->relationLoaded('template') ? $step->getRelation('template') : null;

            $summaries[] = [
                'number' => (int) $step->step_number,
                'title' => (string) $step->title,
                'instruction' => (string) $step->instruction,
                'destination_action' => $step->destination_action?->value,
                'tool' => $tool instanceof Tool && PublishedToolVisibility::allows($tool) ? [
                    'id' => (string) $tool->public_id,
                    'name' => (string) $tool->name,
                    'url' => (string) $tool->external_url,
                ] : null,
                'prompt' => $prompt instanceof PromptTemplate && PublishedPromptVisibility::allows($prompt) ? [
                    'id' => (string) $prompt->public_id,
                    'title' => (string) $prompt->title,
                ] : null,
                'template' => $template instanceof Template && PublishedTemplateVisibility::allows($template) ? [
                    'id' => (string) $template->public_id,
                    'title' => (string) $template->title,
                ] : null,
            ];
        }

        return $summaries;
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
