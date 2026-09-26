<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Courses\Models\Course;
use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Planner\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
final class TaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $course = $this->relationLoaded('course') ? $this->course : null;
        $status = $this->getAttribute('status');

        return [
            'id' => (string) $this->public_id,
            'version' => $this->version,
            'title' => $this->title,
            'description' => $this->description,
            'course' => $course instanceof Course ? $this->courseReference($course) : null,
            'due_at' => $this->timestamp($this->due_at),
            'status' => $status instanceof TaskStatus ? $status->value : (string) $status,
            'completed_at' => $this->timestamp($this->completed_at),
            'archive_status' => $this->archived_at === null ? 'active' : 'archived',
            'archived_at' => $this->timestamp($this->archived_at),
            'created_at' => $this->timestamp($this->created_at),
            'updated_at' => $this->timestamp($this->updated_at),
        ];
    }

    /** @return array{id: string, version: int, title: string, code: ?string, archive_status: string} */
    private function courseReference(Course $course): array
    {
        return [
            'id' => (string) $course->public_id,
            'version' => $course->version,
            'title' => $course->title,
            'code' => $course->code,
            'archive_status' => $course->archived_at === null ? 'active' : 'archived',
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
