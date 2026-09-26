<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Exceptions\BrainVersionConflict;
use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteKnowledgeItemAction
{
    public function __construct(private FindOwnedKnowledgeItem $items) {}

    public function execute(User $user, string $publicId, int $expectedVersion): void
    {
        try {
            DB::transaction(function () use ($user, $publicId, $expectedVersion): void {
                $item = $this->items->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('delete', $item);

                if ($item->version !== $expectedVersion) {
                    throw new BrainVersionConflict;
                }

                $item->delete();
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.delete');
        }
    }
}
