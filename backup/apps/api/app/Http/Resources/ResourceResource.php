<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Courses\Models\Course;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Models\Resource as AcademicResource;
use App\Domains\Resources\Models\StoredFile;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AcademicResource */
final class ResourceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $course = $this->relationLoaded('course') ? $this->course : null;
        $file = $this->relationLoaded('storedFile') ? $this->storedFile : null;
        $kind = $this->getAttribute('kind');

        return [
            'id' => (string) $this->public_id,
            'kind' => $kind instanceof ResourceKind ? $kind->value : (string) $kind,
            'title' => $this->title,
            'description' => $this->description,
            'topic' => $this->topic_label,
            'url' => $this->source_url,
            'course' => $course instanceof Course ? $this->courseReference($course) : null,
            'version' => $this->version,
            'file' => $file instanceof StoredFile ? $this->fileReference($file) : null,
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

    /** @return array{public_id: string, original_name: string, declared_mime_type: string, verified_mime_type: ?string, expected_size: int, verified_size: ?int, status: string, ready_at: ?string} */
    private function fileReference(StoredFile $file): array
    {
        $status = $file->getAttribute('status');

        return [
            'public_id' => (string) $file->public_id,
            'original_name' => $file->original_name,
            'declared_mime_type' => $file->declared_mime_type,
            'verified_mime_type' => $file->verified_mime_type,
            'expected_size' => $file->expected_size,
            'verified_size' => $file->verified_size,
            'status' => $status instanceof StoredFileStatus ? $status->value : (string) $status,
            'ready_at' => $this->timestamp($file->ready_at),
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
