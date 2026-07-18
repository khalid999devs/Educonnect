<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateVersion;
use App\Domains\Tools\Models\ToolCategory;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The curator's view of a template: content, the latest version body, and
 * lifecycle state in any state (no viewer preference state).
 *
 * @mixin Template
 */
final class AdminTemplateResource extends JsonResource
{
    use FormatsApiTimestamps;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $category = $this->whenLoaded('category');
        $latest = $this->relationLoaded('latestVersion') ? $this->latestVersion : null;

        return [
            'id' => (string) $this->public_id,
            'title' => (string) $this->title,
            'category' => $category instanceof ToolCategory ? [
                'slug' => (string) $category->slug,
                'name' => (string) $category->name,
            ] : null,
            'summary' => (string) $this->summary,
            'badge' => $this->badge->value,
            'integrity_note' => (string) $this->integrity_note,
            'provenance' => (string) $this->provenance,
            'latest_version' => $latest instanceof TemplateVersion ? [
                'number' => (int) $latest->version_number,
                'format' => $latest->format->value,
                'body' => (string) $latest->body,
            ] : null,
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
