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

final readonly class UpdateKnowledgeNoteAction
{
    public function __construct(private FindOwnedKnowledgeItem $items) {}

    public function execute(
        User $user,
        string $itemPublicId,
        string $notePublicId,
        string $body,
        int $expectedVersion,
    ): KnowledgeNote {
        try {
            return DB::transaction(function () use ($user, $itemPublicId, $notePublicId, $body, $expectedVersion): KnowledgeNote {
                $item = $this->items->execute($user, $itemPublicId);
                Gate::forUser($user)->authorize('update', $item);
                $note = $this->findNote($item->getKey(), $notePublicId);

                if ($note->body === $body) {
                    return $note;
                }

                if ($note->version !== $expectedVersion) {
                    throw new BrainVersionConflict;
                }

                $note->forceFill([
                    'body' => $body,
                    'version' => $note->version + 1,
                ])->save();

                return $note->refresh();
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.note.update');
        }
    }

    private function findNote(int|string $itemId, string $notePublicId): KnowledgeNote
    {
        $note = KnowledgeNote::query()
            ->where('knowledge_item_id', $itemId)
            ->where('public_id', $notePublicId)
            ->lockForUpdate()
            ->first();

        if (! $note instanceof KnowledgeNote) {
            throw new NotFoundHttpException;
        }

        return $note;
    }
}
