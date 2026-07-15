<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Tools\Enums\ToolPreferenceState;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tool */
final class ToolResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $category = $this->relationLoaded('category') ? $this->getRelation('category') : null;
        $preference = $this->getAttribute('viewer_preference_state');
        $state = $preference instanceof ToolPreferenceState
            ? $preference
            : (is_string($preference) ? ToolPreferenceState::tryFrom($preference) : null);

        return [
            'id' => (string) $this->public_id,
            'name' => (string) $this->name,
            'category' => $category instanceof ToolCategory ? [
                'key' => (string) $category->slug,
                'name' => (string) $category->name,
            ] : null,
            'purpose' => (string) $this->purpose,
            'selection_reason' => (string) $this->selection_reason,
            'use_cases' => $this->useCases(),
            'usage_guidance' => (string) $this->usage_guidance,
            'limitations' => (string) $this->limitations,
            'cost_note' => (string) $this->cost_note,
            'privacy_note' => (string) $this->privacy_note,
            'url' => (string) $this->external_url,
            'provenance' => (string) $this->provenance,
            'last_reviewed_at' => $this->timestamp($this->last_reviewed_at),
            'viewer_state' => [
                'saved' => $state === ToolPreferenceState::Saved,
                'dismissed' => $state === ToolPreferenceState::Dismissed,
            ],
        ];
    }

    /** @return list<string> */
    private function useCases(): array
    {
        $value = $this->getAttribute('use_cases');

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
