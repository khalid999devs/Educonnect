<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Exceptions\BrainVersionConflict;
use App\Domains\SecondBrain\Queries\FindOwnedCollection;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteCollectionAction
{
    public function __construct(private FindOwnedCollection $collections) {}

    public function execute(User $user, string $publicId, int $expectedVersion): void
    {
        try {
            DB::transaction(function () use ($user, $publicId, $expectedVersion): void {
                $collection = $this->collections->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('delete', $collection);

                if ($collection->version !== $expectedVersion) {
                    throw new BrainVersionConflict;
                }

                $collection->delete();
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'collection.delete');
        }
    }
}
