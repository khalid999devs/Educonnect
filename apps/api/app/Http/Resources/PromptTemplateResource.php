<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PromptTemplate */
final class PromptTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $category = $this->relationLoaded('category') ? $this->getRelation('category') : null;
        $preference = $this->getAttribute('viewer_preference_state');
        $state = $preference instanceof GuidancePreferenceState
            ? $preference
            : (is_string($preference) ? GuidancePreferenceState::tryFrom($preference) : null);
        $copyCount = $this->getAttribute('viewer_copy_count');

        return [
            'id' => (string) $this->public_id,
            'title' => (string) $this->title,
            'category' => $category instanceof ToolCategory ? [
                'key' => (string) $category->slug,
                'name' => (string) $category->name,
            ] : null,
            'purpose' => (string) $this->purpose,
            'template_body' => (string) $this->template_body,
            'placeholders' => $this->placeholderList(),
            'expected_output' => (string) $this->expected_output,
            'integrity_note' => (string) $this->integrity_note,
            'provenance' => (string) $this->provenance,
            'related_tools' => $this->relatedToolSummaries(),
            'last_reviewed_at' => $this->timestamp($this->last_reviewed_at),
            'viewer_state' => [
                'saved' => $state === GuidancePreferenceState::Saved,
                'dismissed' => $state === GuidancePreferenceState::Dismissed,
                'copy_count' => is_numeric($copyCount) ? (int) $copyCount : 0,
            ],
        ];
    }

    /** @return list<string> */
    private function placeholderList(): array
    {
        $value = $this->getAttribute('placeholders');

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /** @return list<array{id: string, name: string, url: string}> */
    private function relatedToolSummaries(): array
    {
        if (! $this->relationLoaded('relatedTools')) {
            return [];
        }

        $summaries = [];

        foreach ($this->getRelation('relatedTools') as $tool) {
            if (! $tool instanceof Tool) {
                continue;
            }

            $summaries[] = [
                'id' => (string) $tool->public_id,
                'name' => (string) $tool->name,
                'url' => (string) $tool->external_url,
            ];
        }

        return $summaries;
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
