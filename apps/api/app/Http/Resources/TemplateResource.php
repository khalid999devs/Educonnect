<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateVersion;
use App\Domains\Tools\Models\ToolCategory;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Template */
final class TemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $category = $this->relationLoaded('category') ? $this->getRelation('category') : null;
        $latestVersion = $this->relationLoaded('latestVersion') ? $this->getRelation('latestVersion') : null;
        $preference = $this->getAttribute('viewer_preference_state');
        $state = $preference instanceof GuidancePreferenceState
            ? $preference
            : (is_string($preference) ? GuidancePreferenceState::tryFrom($preference) : null);
        $activeCopyCount = $this->getAttribute('viewer_active_copy_count');

        return [
            'id' => (string) $this->public_id,
            'title' => (string) $this->title,
            'category' => $category instanceof ToolCategory ? [
                'key' => (string) $category->slug,
                'name' => (string) $category->name,
            ] : null,
            'summary' => (string) $this->summary,
            'badge' => $this->badge->value,
            'integrity_note' => (string) $this->integrity_note,
            'provenance' => (string) $this->provenance,
            'latest_version' => $latestVersion instanceof TemplateVersion ? [
                'number' => (int) $latestVersion->version_number,
                'format' => $latestVersion->format->value,
                'body' => (string) $latestVersion->body,
            ] : null,
            'last_reviewed_at' => $this->timestamp($this->last_reviewed_at),
            'viewer_state' => [
                'saved' => $state === GuidancePreferenceState::Saved,
                'dismissed' => $state === GuidancePreferenceState::Dismissed,
                'active_copy_count' => is_numeric($activeCopyCount) ? (int) $activeCopyCount : 0,
            ],
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
