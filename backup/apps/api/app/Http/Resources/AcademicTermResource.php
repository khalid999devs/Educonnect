<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Courses\Models\AcademicTerm;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AcademicTerm */
final class AcademicTermResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->public_id,
            'version' => $this->version,
            'label' => $this->label,
            'starts_on' => $this->date($this->starts_on),
            'ends_on' => $this->date($this->ends_on),
            'course_counts' => [
                'active' => (int) $this->getAttribute('active_courses_count'),
                'archived' => (int) $this->getAttribute('archived_courses_count'),
            ],
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
