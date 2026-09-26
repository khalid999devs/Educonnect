<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full curator's view of a tool: every content field plus lifecycle state
 * and timestamps, in any state (unlike the published-only student ToolResource,
 * this carries no viewer preference state).
 *
 * @mixin Tool
 */
final class AdminToolResource extends JsonResource
{
    use FormatsApiTimestamps;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $category = $this->whenLoaded('category');

        return [
            'id' => (string) $this->public_id,
            'name' => (string) $this->name,
            'category' => $category instanceof ToolCategory ? [
                'slug' => (string) $category->slug,
                'name' => (string) $category->name,
            ] : null,
            'purpose' => (string) $this->purpose,
            'selection_reason' => (string) $this->selection_reason,
            'use_cases' => $this->use_cases ?? [],
            'usage_guidance' => (string) $this->usage_guidance,
            'limitations' => (string) $this->limitations,
            'cost_note' => (string) $this->cost_note,
            'privacy_note' => (string) $this->privacy_note,
            'url' => (string) $this->external_url,
            'provenance' => (string) $this->provenance,
            'state' => $this->state->value,
            'last_reviewed_at' => $this->timestamp($this->last_reviewed_at),
            'published_at' => $this->timestamp($this->published_at),
            'archived_at' => $this->timestamp($this->archived_at),
            'version' => $this->version,
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
            'updated_at' => $this->timestamp($this->getAttribute('updated_at')),
        ];
    }
}
