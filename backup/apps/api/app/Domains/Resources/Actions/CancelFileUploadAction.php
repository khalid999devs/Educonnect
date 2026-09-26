<?php

declare(strict_types=1);

namespace App\Domains\Resources\Actions;

use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Exceptions\ResourcePersistenceFailure;
use App\Domains\Resources\Exceptions\ResourceStateConflict;
use App\Domains\Resources\Exceptions\ResourceVersionConflict;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Resources\Queries\FindOwnedResource;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class CancelFileUploadAction
{
    public function __construct(private FindOwnedResource $resources) {}

    public function execute(User $user, string $publicId, int $expectedVersion): Resource
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $expectedVersion): Resource {
                $resource = $this->resources->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('delete', $resource);
                $storedFile = StoredFile::query()
                    ->where('user_id', $user->getKey())
                    ->where('resource_id', $resource->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($resource->kind !== ResourceKind::File || ! $storedFile instanceof StoredFile) {
                    throw new ResourceStateConflict;
                }

                if ($storedFile->status === StoredFileStatus::DeletionPending) {
                    return $resource->load(['course', 'storedFile']);
                }

                if ($storedFile->status !== StoredFileStatus::Pending) {
                    throw new ResourceStateConflict;
                }

                if ($resource->version !== $expectedVersion) {
                    throw new ResourceVersionConflict;
                }

                $storedFile->forceFill(['status' => StoredFileStatus::DeletionPending])->save();
                $resource->forceFill(['version' => $resource->version + 1])->save();

                return $resource->refresh()->load(['course', 'storedFile']);
            }, 3);
        } catch (QueryException $exception) {
            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.file.cancel');
        }
    }
}
