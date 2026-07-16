<?php

declare(strict_types=1);

namespace App\Domains\Resources\Actions;

use App\Domains\Resources\Data\ResourceDeleteResult;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Exceptions\ResourcePersistenceFailure;
use App\Domains\Resources\Exceptions\ResourceStateConflict;
use App\Domains\Resources\Exceptions\ResourceVersionConflict;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Resources\Queries\FindOwnedResource;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteResourceAction
{
    public function __construct(private FindOwnedResource $resources) {}

    public function execute(User $user, string $publicId, int $expectedVersion): ResourceDeleteResult
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $expectedVersion): ResourceDeleteResult {
                $resource = $this->resources->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('delete', $resource);

                $isKnowledgeSource = DB::table('knowledge_items')
                    ->where('resource_id', $resource->getKey())
                    ->exists();

                if ($isKnowledgeSource) {
                    throw new ResourceStateConflict;
                }

                if ($resource->kind === ResourceKind::Link) {
                    if ($resource->version !== $expectedVersion) {
                        throw new ResourceVersionConflict;
                    }

                    $resource->deleteOrFail();

                    return new ResourceDeleteResult(false, null);
                }

                $storedFile = StoredFile::query()
                    ->where('user_id', $user->getKey())
                    ->where('resource_id', $resource->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $storedFile instanceof StoredFile) {
                    throw new ResourceStateConflict;
                }

                if ($storedFile->status === StoredFileStatus::DeletionPending) {
                    return new ResourceDeleteResult(true, $resource->load(['course', 'storedFile']));
                }

                if ($resource->version !== $expectedVersion) {
                    throw new ResourceVersionConflict;
                }

                $cleanupAfter = $storedFile->upload_key === null
                    ? CarbonImmutable::now()->startOfSecond()
                    : $storedFile->cleanup_after;

                if (! $cleanupAfter instanceof CarbonImmutable) {
                    throw new ResourceStateConflict;
                }

                $storedFile->forceFill([
                    'status' => StoredFileStatus::DeletionPending,
                    'cleanup_after' => $cleanupAfter,
                ])->save();
                $resource->forceFill(['version' => $resource->version + 1])->save();

                return new ResourceDeleteResult(
                    true,
                    $resource->refresh()->load(['course', 'storedFile']),
                );
            }, 3);
        } catch (QueryException $exception) {
            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.delete');
        }
    }
}
