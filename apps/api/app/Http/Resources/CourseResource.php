<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Models\Course;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Course */
final class CourseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $term = $this->relationLoaded('academicTerm') ? $this->academicTerm : null;

        return [
            'id' => (string) $this->public_id,
            'version' => $this->version,
            'title' => $this->title,
            'code' => $this->code,
            'description' => $this->description,
            'term' => $term instanceof AcademicTerm ? [
                'id' => (string) $term->public_id,
                'version' => $term->version,
                'label' => $term->label,
                'starts_on' => $this->date($term->starts_on),
                'ends_on' => $this->date($term->ends_on),
            ] : null,
            'status' => $this->archived_at === null ? 'active' : 'archived',
            'archived_at' => $this->timestamp($this->archived_at),
            'created_at' => $this->timestamp($this->created_at),
            'updated_at' => $this->timestamp($this->updated_at),
        ];
    }

    private function date(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->format('Y-m-d') : null;
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
