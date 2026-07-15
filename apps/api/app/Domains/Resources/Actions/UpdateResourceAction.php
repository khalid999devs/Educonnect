<?php

declare(strict_types=1);

namespace App\Domains\Resources\Actions;

use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Exceptions\InvalidResourceLink;
use App\Domains\Resources\Exceptions\InvalidResourceMutation;
use App\Domains\Resources\Exceptions\ResourcePersistenceFailure;
use App\Domains\Resources\Exceptions\ResourceStateConflict;
use App\Domains\Resources\Exceptions\ResourceVersionConflict;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Resources\Queries\FindOwnedResource;
use App\Domains\Resources\Support\ResourceUrlValidator;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateResourceAction
{
    public function __construct(
        private FindOwnedResource $resources,
        private FindOwnedCourse $courses,
        private ResourceUrlValidator $urls,
    ) {}

    /** @param array{kind: ResourceKind, title: string, description: ?string, course_id: ?string, topic_label: ?string, url?: string} $data */
    public function execute(User $user, string $publicId, array $data, int $expectedVersion): Resource
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $data, $expectedVersion): Resource {
                $resource = $this->resources->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $resource);
                $storedFile = $resource->storedFile;

                if ($data['kind'] !== $resource->kind) {
                    throw new InvalidResourceMutation;
                }

                if ($storedFile instanceof StoredFile
                    && $storedFile->status === StoredFileStatus::DeletionPending) {
                    throw new ResourceStateConflict;
                }

                $course = $data['course_id'] === null
                    ? null
                    : $this->courses->execute($user, $data['course_id'], lockForUpdate: true);
                $sourceUrl = $this->sourceUrl($resource, $data);
                $desired = [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'course_id' => $course?->getKey(),
                    'topic_label' => $data['topic_label'],
                    'source_url' => $sourceUrl,
                ];

                if ($this->canonical($resource) === $desired) {
                    return $resource;
                }

                if ($course?->archived_at !== null) {
                    throw new ResourceStateConflict;
                }

                if ($resource->version !== $expectedVersion) {
                    throw new ResourceVersionConflict;
                }

                $resource->forceFill([
                    ...$desired,
                    'version' => $resource->version + 1,
                ])->save();

                return $resource->refresh()->load(['course', 'storedFile']);
            }, 3);
        } catch (QueryException $exception) {
            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.update');
        }
    }

    /** @param array{kind: ResourceKind, url?: string} $data */
    private function sourceUrl(Resource $resource, array $data): ?string
    {
        if ($resource->kind === ResourceKind::File) {
            if (array_key_exists('url', $data)) {
                throw new InvalidResourceLink;
            }

            return null;
        }

        if (! is_string($data['url'] ?? null)) {
            throw new InvalidResourceLink;
        }

        return $this->urls->validate($data['url']);
    }

    /** @return array{title: string, description: ?string, course_id: mixed, topic_label: ?string, source_url: ?string} */
    private function canonical(Resource $resource): array
    {
        return [
            'title' => $resource->title,
            'description' => $resource->description,
            'course_id' => $resource->course_id,
            'topic_label' => $resource->topic_label,
            'source_url' => $resource->source_url,
        ];
    }
}
