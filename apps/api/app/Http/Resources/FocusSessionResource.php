<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Courses\Models\Course;
use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FocusSession */
final class FocusSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $task = $this->relationLoaded('task') ? $this->task : null;
        $course = $this->relationLoaded('course') ? $this->course : null;

        return [
            'id' => (string) $this->public_id,
            'version' => $this->version,
            'task' => $task instanceof Task ? $this->taskReference($task) : null,
            'course' => $course instanceof Course ? $this->courseReference($course) : null,
            'starts_at' => $this->timestamp($this->starts_at),
            'ends_at' => $this->timestamp($this->ends_at),
            'note' => $this->note,
            'created_at' => $this->timestamp($this->created_at),
            'updated_at' => $this->timestamp($this->updated_at),
        ];
    }

    /** @return array{id: string, version: int, title: string, status: string, archive_status: string} */
    private function taskReference(Task $task): array
    {
        $status = $task->getAttribute('status');

        return [
            'id' => (string) $task->public_id,
            'version' => $task->version,
            'title' => $task->title,
            'status' => $status instanceof TaskStatus ? $status->value : (string) $status,
            'archive_status' => $task->archived_at === null ? 'active' : 'archived',
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
