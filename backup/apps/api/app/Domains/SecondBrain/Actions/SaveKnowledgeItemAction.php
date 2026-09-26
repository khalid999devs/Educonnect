<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SaveKnowledgeItemAction
{
    public function __construct(private FindOwnedKnowledgeItem $items) {}

    public function execute(User $user, string $publicId): KnowledgeItem
    {
        try {
            return DB::transaction(function () use ($user, $publicId): KnowledgeItem {
                $item = $this->items->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $item);

                // Idempotent: saving an already-saved item is a no-op success. The
                // original bookmark timestamp stays put rather than moving forward.
                if ($item->getAttribute('saved_at') === null) {
                    $item->forceFill(['saved_at' => now('UTC')])->save();
                }

                return $item->refresh()->load(['resource', 'intakeItem', 'tags']);
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'brain.item.save');
        }
    }
}
