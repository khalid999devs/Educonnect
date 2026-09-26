<?php

declare(strict_types=1);

namespace App\Domains\Resources\Actions;

use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Exceptions\InvalidResourceFile;
use App\Domains\Resources\Exceptions\ResourcePersistenceFailure;
use App\Domains\Resources\Exceptions\ResourceStateConflict;
use App\Domains\Resources\Exceptions\ResourceVersionConflict;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Resources\Queries\FindOwnedResource;
use App\Domains\Resources\Support\ResourceFileInspector;
use App\Domains\Resources\Support\ResourceStorage;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class ConfirmFileUploadAction
{
    public function __construct(
        private FindOwnedResource $resources,
        private ResourceStorage $storage,
        private ResourceFileInspector $files,
    ) {}

    public function execute(User $user, string $publicId, int $expectedVersion): Resource
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $expectedVersion): Resource {
                $resource = $this->resources->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $resource);
                $storedFile = StoredFile::query()
                    ->where('user_id', $user->getKey())
                    ->where('resource_id', $resource->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($resource->kind !== ResourceKind::File || ! $storedFile instanceof StoredFile) {
                    throw new ResourceStateConflict;
                }

                if ($storedFile->status === StoredFileStatus::Ready) {
                    return $resource->load(['course', 'storedFile']);
                }

                if ($storedFile->status !== StoredFileStatus::Pending
                    || ! is_string($storedFile->upload_key)
                    || ! $storedFile->cleanup_after instanceof CarbonImmutable
                    || CarbonImmutable::now()->greaterThanOrEqualTo(
                        $storedFile->upload_expires_at->addSeconds($this->storage->cleanupGraceSeconds()),
                    )) {
                    throw new ResourceStateConflict;
                }

                if ($resource->version !== $expectedVersion) {
                    throw new ResourceVersionConflict;
                }

                if (! $this->storage->exists($storedFile->upload_key)) {
                    throw new ResourceStateConflict;
                }

                if ($this->storage->size($storedFile->upload_key) !== $storedFile->expected_size) {
                    throw new InvalidResourceFile;
                }

                // The signed staging key can still be overwritten until its grant expires.
                // Verify the server-owned final copy so a PUT racing confirmation cannot
                // replace inspected bytes before they become downloadable.
                $this->storage->copy($storedFile->upload_key, $storedFile->object_key);

                if ($this->storage->size($storedFile->object_key) !== $storedFile->expected_size) {
                    throw new InvalidResourceFile;
                }

                $stream = $this->storage->readStream($storedFile->object_key);

                try {
                    $inspection = $this->files->inspect(
                        $stream,
                        $storedFile->declared_mime_type,
                        $storedFile->expected_size,
                        $storedFile->sha256,
                    );
                } finally {
                    fclose($stream);
                }

                $readyAt = CarbonImmutable::now()->startOfSecond();
                $storedFile->forceFill([
                    'verified_mime_type' => $inspection->mimeType,
                    'verified_size' => $inspection->size,
                    'status' => StoredFileStatus::Ready,
                    'ready_at' => $readyAt,
                ])->save();
                $resource->forceFill(['version' => $resource->version + 1])->save();

                return $resource->refresh()->load(['course', 'storedFile']);
            }, 3);
        } catch (QueryException $exception) {
            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.file.confirm');
        }
    }
}
