<?php

declare(strict_types=1);

namespace App\Domains\Resources\Actions;

use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Resources\Data\UploadGrantResult;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Exceptions\ResourcePersistenceFailure;
use App\Domains\Resources\Exceptions\ResourceStateConflict;
use App\Domains\Resources\Exceptions\ResourceStorageFailure;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Resources\Support\ResourceFileInspector;
use App\Domains\Resources\Support\ResourceStorage;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class InitiateFileUploadAction
{
    public function __construct(
        private FindOwnedCourse $courses,
        private ResourceFileInspector $files,
        private ResourceStorage $storage,
    ) {}

    /**
     * @param array{
     *   title: string,
     *   description: ?string,
     *   course_id: ?string,
     *   topic_label: ?string,
     *   original_name: string,
     *   mime_type: string,
     *   size: int,
     *   sha256: string
     * } $data
     */
    public function execute(User $user, array $data): UploadGrantResult
    {
        Gate::forUser($user)->authorize('create', Resource::class);
        Gate::forUser($user)->authorize('create', StoredFile::class);
        $this->files->assertUploadMetadata(
            $data['original_name'],
            $data['mime_type'],
            $data['size'],
            $data['sha256'],
        );

        $now = CarbonImmutable::now()->startOfSecond();
        $expiresAt = $now->addSeconds($this->storage->uploadTtlSeconds());
        $cleanupAfter = $expiresAt->addSeconds($this->storage->cleanupGraceSeconds());

        try {
            $resource = DB::transaction(function () use ($user, $data, $expiresAt, $cleanupAfter): Resource {
                $course = $data['course_id'] === null
                    ? null
                    : $this->courses->execute($user, $data['course_id'], lockForUpdate: true);

                if ($course?->archived_at !== null) {
                    throw new ResourceStateConflict;
                }

                $resource = new Resource;
                $resource->forceFill([
                    'user_id' => $user->getKey(),
                    'course_id' => $course?->getKey(),
                    'kind' => ResourceKind::File,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'topic_label' => $data['topic_label'],
                    'source_url' => null,
                    'version' => 1,
                ])->save();

                $storedFile = new StoredFile;
                $storedFile->forceFill([
                    'user_id' => $user->getKey(),
                    'resource_id' => $resource->getKey(),
                    'original_name' => $data['original_name'],
                    'declared_mime_type' => $data['mime_type'],
                    'expected_size' => $data['size'],
                    'sha256' => $data['sha256'],
                    'upload_key' => 'resources/v1/uploads/'.bin2hex(random_bytes(16)),
                    'object_key' => 'resources/v1/objects/'.bin2hex(random_bytes(16)),
                    'verified_mime_type' => null,
                    'verified_size' => null,
                    'status' => StoredFileStatus::Pending,
                    'upload_expires_at' => $expiresAt,
                    'cleanup_after' => $cleanupAfter,
                    'ready_at' => null,
                ])->save();

                return $resource->load(['course', 'storedFile']);
            }, 3);
        } catch (QueryException $exception) {
            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.file.initiate');
        }

        $storedFile = $resource->storedFile;

        if (! $storedFile instanceof StoredFile || ! is_string($storedFile->upload_key)) {
            throw new ResourceStateConflict;
        }

        try {
            $grant = $this->storage->temporaryUploadUrl(
                $storedFile->upload_key,
                $expiresAt,
                $storedFile->declared_mime_type,
                $storedFile->expected_size,
            );
        } catch (ResourceStorageFailure $exception) {
            $this->markSigningFailure($user, $resource);

            throw $exception;
        }

        return new UploadGrantResult(
            $resource,
            $grant->url,
            $grant->headers,
            $expiresAt,
        );
    }

    private function markSigningFailure(User $user, Resource $resource): void
    {
        try {
            DB::transaction(function () use ($user, $resource): void {
                $lockedResource = Resource::query()
                    ->where('user_id', $user->getKey())
                    ->whereKey($resource->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $lockedResource instanceof Resource) {
                    return;
                }

                $storedFile = StoredFile::query()
                    ->where('user_id', $user->getKey())
                    ->where('resource_id', $lockedResource->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $storedFile instanceof StoredFile || $storedFile->status !== StoredFileStatus::Pending) {
                    return;
                }

                $storedFile->forceFill(['status' => StoredFileStatus::DeletionPending])->save();
                $lockedResource->forceFill(['version' => $lockedResource->version + 1])->save();
            }, 3);
        } catch (QueryException $exception) {
            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.file.initiate');
        }
    }
}
