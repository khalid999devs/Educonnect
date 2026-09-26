<?php

declare(strict_types=1);

namespace App\Domains\Resources\Actions;

use App\Domains\Resources\Data\ResourceReconciliationResult;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Exceptions\ResourcePersistenceFailure;
use App\Domains\Resources\Exceptions\ResourceStorageFailure;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Resources\Support\ResourceStorage;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class ReconcileResourceStorageAction
{
    public function __construct(private ResourceStorage $storage) {}

    public function execute(int $limit = 100): ResourceReconciliationResult
    {
        $limit = max(1, min($limit, 500));
        $now = CarbonImmutable::now()->startOfSecond();

        try {
            /** @var list<array{id: int, resource_id: int}> $candidates */
            $candidates = StoredFile::query()
                ->whereNotNull('cleanup_after')
                ->where('cleanup_after', '<=', $now)
                ->orderBy('cleanup_after')
                ->orderBy('id')
                ->limit($limit)
                ->get(['id', 'resource_id'])
                ->map(static fn (StoredFile $file): array => [
                    'id' => (int) $file->getKey(),
                    'resource_id' => (int) $file->resource_id,
                ])
                ->all();
        } catch (QueryException $exception) {
            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.reconcile');
        }

        $cleaned = 0;
        $failed = 0;

        foreach ($candidates as $candidate) {
            try {
                $didClean = DB::transaction(function () use ($candidate, $now): bool {
                    $resource = Resource::query()
                        ->whereKey($candidate['resource_id'])
                        ->lock('FOR UPDATE SKIP LOCKED')
                        ->first();

                    if (! $resource instanceof Resource) {
                        return false;
                    }

                    $storedFile = StoredFile::query()
                        ->whereKey($candidate['id'])
                        ->where('resource_id', $resource->getKey())
                        ->lockForUpdate()
                        ->first();

                    if (! $storedFile instanceof StoredFile
                        || ! $storedFile->cleanup_after instanceof CarbonImmutable
                        || $storedFile->cleanup_after->greaterThan($now)) {
                        return false;
                    }

                    if ($storedFile->status === StoredFileStatus::Ready) {
                        if (! is_string($storedFile->upload_key)) {
                            return false;
                        }

                        $this->storage->delete([$storedFile->upload_key]);

                        if (! $storedFile->cleanup_started_at instanceof CarbonImmutable) {
                            $storedFile->forceFill([
                                'cleanup_started_at' => $now,
                                'cleanup_after' => $now->addSeconds($this->storage->lateUploadReapSeconds()),
                                'cleanup_failures' => 0,
                            ])->save();

                            return true;
                        }

                        $storedFile->forceFill([
                            'upload_key' => null,
                            'cleanup_after' => null,
                            'cleanup_started_at' => null,
                            'cleanup_failures' => 0,
                        ])->save();

                        return true;
                    }

                    $this->storage->delete([$storedFile->upload_key, $storedFile->object_key]);

                    if (is_string($storedFile->upload_key)
                        && ! $storedFile->cleanup_started_at instanceof CarbonImmutable) {
                        $wasPending = $storedFile->status === StoredFileStatus::Pending;
                        $storedFile->forceFill([
                            'status' => StoredFileStatus::DeletionPending,
                            'cleanup_started_at' => $now,
                            'cleanup_after' => $now->addSeconds($this->storage->lateUploadReapSeconds()),
                            'cleanup_failures' => 0,
                        ])->save();

                        if ($wasPending) {
                            $resource->forceFill(['version' => $resource->version + 1])->save();
                        }

                        return true;
                    }

                    $storedFile->forceFill(['purge_ready_at' => $now])->save();
                    $storedFile->deleteOrFail();
                    $resource->deleteOrFail();

                    return true;
                }, 3);

                if ($didClean) {
                    $cleaned++;
                }
            } catch (ResourceStorageFailure) {
                $this->deferFailure($candidate['id'], $now);
                $failed++;
            } catch (QueryException $exception) {
                throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.reconcile');
            }
        }

        return new ResourceReconciliationResult(count($candidates), $cleaned, $failed);
    }

    private function deferFailure(int $storedFileId, CarbonImmutable $now): void
    {
        try {
            DB::transaction(function () use ($storedFileId, $now): void {
                $storedFile = StoredFile::query()
                    ->whereKey($storedFileId)
                    ->where('cleanup_after', '<=', $now)
                    ->lockForUpdate()
                    ->first();

                if (! $storedFile instanceof StoredFile) {
                    return;
                }

                $failures = min($storedFile->cleanup_failures + 1, 16);
                $backoffSeconds = min(300 * (1 << min($failures - 1, 6)), 21_600);
                $storedFile->forceFill([
                    'cleanup_failures' => $failures,
                    'cleanup_after' => $now->addSeconds($backoffSeconds),
                ])->save();
            }, 3);
        } catch (QueryException $exception) {
            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.reconcile');
        }
    }
}
