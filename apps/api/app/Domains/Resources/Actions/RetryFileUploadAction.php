<?php

declare(strict_types=1);

namespace App\Domains\Resources\Actions;

use App\Domains\Resources\Data\UploadGrantResult;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Exceptions\ResourcePersistenceFailure;
use App\Domains\Resources\Exceptions\ResourceStateConflict;
use App\Domains\Resources\Exceptions\ResourceVersionConflict;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Resources\Queries\FindOwnedResource;
use App\Domains\Resources\Support\ResourceStorage;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class RetryFileUploadAction
{
    public function __construct(
        private FindOwnedResource $resources,
        private ResourceStorage $storage,
    ) {}

    public function execute(User $user, string $publicId, int $expectedVersion): UploadGrantResult
    {
        $expiresAt = CarbonImmutable::now()->startOfSecond()
            ->addSeconds($this->storage->uploadTtlSeconds());
        $cleanupAfter = $expiresAt->addSeconds($this->storage->cleanupGraceSeconds());

        try {
            $resource = DB::transaction(function () use (
                $user,
                $publicId,
                $expectedVersion,
                $expiresAt,
                $cleanupAfter,
            ): Resource {
                $resource = $this->resources->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $resource);
                $storedFile = StoredFile::query()
                    ->where('user_id', $user->getKey())
                    ->where('resource_id', $resource->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($resource->kind !== ResourceKind::File
                    || ! $storedFile instanceof StoredFile
                    || $storedFile->status !== StoredFileStatus::Pending
                    || ! is_string($storedFile->upload_key)) {
                    throw new ResourceStateConflict;
                }

                if ($resource->version !== $expectedVersion) {
                    throw new ResourceVersionConflict;
                }

                $storedFile->forceFill([
                    'upload_expires_at' => $expiresAt,
                    'cleanup_after' => $cleanupAfter,
                ])->save();
                $resource->forceFill(['version' => $resource->version + 1])->save();

                return $resource->refresh()->load(['course', 'storedFile']);
            }, 3);
        } catch (QueryException $exception) {
            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.file.retry');
        }

        $storedFile = $resource->storedFile;

        if (! $storedFile instanceof StoredFile || ! is_string($storedFile->upload_key)) {
            throw new ResourceStateConflict;
        }

        $grant = $this->storage->temporaryUploadUrl(
            $storedFile->upload_key,
            $expiresAt,
            $storedFile->declared_mime_type,
            $storedFile->expected_size,
        );

        return new UploadGrantResult(
            $resource,
            $grant->url,
            $grant->headers,
            $expiresAt,
        );
    }
}
