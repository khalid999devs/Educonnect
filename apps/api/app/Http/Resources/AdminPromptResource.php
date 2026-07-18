<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full curator's view of a prompt template: every content field plus its
 * related tools, lifecycle state, and timestamps, in any state (unlike the
 * published-only student PromptTemplateResource, this carries no viewer state).
 *
 * @mixin PromptTemplate
 */
final class AdminPromptResource extends JsonResource
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
            'purpose' => (string) $this->purpose,
            'template_body' => (string) $this->template_body,
            'placeholders' => $this->placeholderList(),
            'expected_output' => (string) $this->expected_output,
            'integrity_note' => (string) $this->integrity_note,
            'provenance' => (string) $this->provenance,
            'related_tools' => $this->relatedToolSummaries(),
            'state' => $this->state->value,
            'last_reviewed_at' => $this->timestamp($this->last_reviewed_at),
            'published_at' => $this->timestamp($this->published_at),
            'archived_at' => $this->timestamp($this->archived_at),
            'version' => $this->version,
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
            'updated_at' => $this->timestamp($this->getAttribute('updated_at')),
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

    /** @return list<array{id: string, name: string}> */
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
            ];
        }

        return $summaries;
    }
}
