<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Exceptions\BrainStateConflict;
use App\Domains\SecondBrain\Models\KnowledgeLink;
use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CreateKnowledgeLinkAction
{
    public function __construct(private FindOwnedKnowledgeItem $items) {}

    public function execute(
        User $user,
        string $fromItemPublicId,
        string $toItemPublicId,
        string $relationType,
    ): KnowledgeLink {
        try {
            return DB::transaction(function () use ($user, $fromItemPublicId, $toItemPublicId, $relationType): KnowledgeLink {
                $fromItem = $this->items->execute($user, $fromItemPublicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $fromItem);

                if ($fromItemPublicId === $toItemPublicId) {
                    throw ValidationException::withMessages([
                        'target_id' => ['A knowledge item cannot be connected to itself.'],
                    ]);
                }

                $toItem = $this->items->execute($user, $toItemPublicId);
                $exists = KnowledgeLink::query()
                    ->where('from_item_id', $fromItem->getKey())
                    ->where('to_item_id', $toItem->getKey())
                    ->exists();

                if ($exists) {
                    throw new BrainStateConflict;
                }

                $link = new KnowledgeLink;
                $link->forceFill([
                    'user_id' => $user->getKey(),
                    'from_item_id' => $fromItem->getKey(),
                    'to_item_id' => $toItem->getKey(),
                    'relation_type' => $relationType,
                ])->save();

                return $link->load('toItem');
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.link.create');
        }
    }
}
