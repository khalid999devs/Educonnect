<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Courses\Models\Course;
use App\Domains\Resources\Data\ResourceDirectory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ResourceDirectory */
final class ResourceDirectoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $course = $this->course;

        return [
            'kind' => $this->kind,
            'course' => $course instanceof Course ? $this->courseReference($course) : null,
            'resource_count' => $this->resourceCount,
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
}
