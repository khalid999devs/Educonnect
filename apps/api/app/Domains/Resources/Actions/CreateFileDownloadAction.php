<?php

declare(strict_types=1);

namespace App\Domains\Resources\Actions;

use App\Domains\Resources\Data\DownloadGrantResult;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Exceptions\ResourceStateConflict;
use App\Domains\Resources\Exceptions\ResourceStorageFailure;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Resources\Queries\FindOwnedResource;
use App\Domains\Resources\Support\ResourceStorage;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class CreateFileDownloadAction
{
    public function __construct(
        private FindOwnedResource $resources,
        private ResourceStorage $storage,
    ) {}

    public function execute(User $user, string $publicId): DownloadGrantResult
    {
        $resource = $this->resources->execute($user, $publicId);
        $storedFile = $resource->storedFile;

        if ($resource->kind !== ResourceKind::File
            || ! $storedFile instanceof StoredFile
            || $storedFile->status !== StoredFileStatus::Ready
            || ! is_string($storedFile->verified_mime_type)) {
            throw new ResourceStateConflict;
        }

        if (! $this->storage->exists($storedFile->object_key)) {
            throw ResourceStorageFailure::fromThrowable(
                new RuntimeException('The verified object is unavailable.'),
                'download.sign',
            );
        }

        $expiresAt = CarbonImmutable::now()->startOfSecond()
            ->addSeconds($this->storage->downloadTtlSeconds());
        $url = $this->storage->temporaryDownloadUrl(
            $storedFile->object_key,
            $expiresAt,
            $storedFile->verified_mime_type,
        );

        return new DownloadGrantResult($resource, $url, $expiresAt);
    }
}
