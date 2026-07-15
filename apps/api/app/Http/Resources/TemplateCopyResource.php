<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Courses\Models\Course;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateVersion;
use App\Domains\Templates\Models\UserTemplateCopy;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserTemplateCopy */
final class TemplateCopyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $template = $this->relationLoaded('template') ? $this->getRelation('template') : null;
        $sourceVersion = $this->relationLoaded('templateVersion') ? $this->getRelation('templateVersion') : null;
        $course = $this->relationLoaded('course') ? $this->getRelation('course') : null;

        return [
            'id' => (string) $this->public_id,
            'destination' => $this->destination->value,
            'course' => $course instanceof Course ? [
                'id' => (string) $course->public_id,
                'title' => (string) $course->title,
            ] : null,
            'source' => [
                'template_id' => $template instanceof Template ? (string) $template->public_id : null,
                'template_title' => $template instanceof Template ? (string) $template->title : null,
                'version_number' => $sourceVersion instanceof TemplateVersion ? (int) $sourceVersion->version_number : null,
            ],
            'title' => (string) $this->title,
            'format' => $this->format->value,
            'body' => (string) $this->body,
            'version' => (int) $this->version,
            'archived_at' => $this->timestamp($this->archived_at),
            'created_at' => $this->timestamp($this->created_at),
            'updated_at' => $this->timestamp($this->updated_at),
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
