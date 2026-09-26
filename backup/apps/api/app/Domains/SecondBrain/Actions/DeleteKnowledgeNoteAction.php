<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Exceptions\BrainVersionConflict;
use App\Domains\SecondBrain\Models\KnowledgeNote;
use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class DeleteKnowledgeNoteAction
{
    public function __construct(private FindOwnedKnowledgeItem $items) {}

    public function execute(User $user, string $itemPublicId, string $notePublicId, int $expectedVersion): void
    {
        try {
            DB::transaction(function () use ($user, $itemPublicId, $notePublicId, $expectedVersion): void {
                $item = $this->items->execute($user, $itemPublicId);
                Gate::forUser($user)->authorize('update', $item);
                $note = KnowledgeNote::query()
                    ->where('knowledge_item_id', $item->getKey())
                    ->where('public_id', $notePublicId)
                    ->lockForUpdate()
                    ->first();

                if (! $note instanceof KnowledgeNote) {
                    throw new NotFoundHttpException;
                }

                if ($note->version !== $expectedVersion) {
                    throw new BrainVersionConflict;
                }

                $note->delete();
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.note.delete');
        }
    }
}
