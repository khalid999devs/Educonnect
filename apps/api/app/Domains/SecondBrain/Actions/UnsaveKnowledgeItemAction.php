<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UnsaveKnowledgeItemAction
{
    public function __construct(private FindOwnedKnowledgeItem $items) {}

    public function execute(User $user, string $publicId): void
    {
        try {
            DB::transaction(function () use ($user, $publicId): void {
                $item = $this->items->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $item);

                // Idempotent: clearing an item that was never saved changes nothing.
                if ($item->getAttribute('saved_at') !== null) {
                    $item->forceFill(['saved_at' => null])->save();
                }
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'brain.item.unsave');
        }
    }
}
