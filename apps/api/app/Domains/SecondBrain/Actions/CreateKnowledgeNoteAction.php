<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\KnowledgeNote;
use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class CreateKnowledgeNoteAction
{
    public function __construct(private FindOwnedKnowledgeItem $items) {}

    public function execute(User $user, string $itemPublicId, string $body): KnowledgeNote
    {
        try {
            return DB::transaction(function () use ($user, $itemPublicId, $body): KnowledgeNote {
                $item = $this->items->execute($user, $itemPublicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $item);

                $note = new KnowledgeNote;
                $note->forceFill([
                    'user_id' => $user->getKey(),
                    'knowledge_item_id' => $item->getKey(),
                    'body' => $body,
                    'version' => 1,
                ])->save();

                return $note;
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.note.create');
        }
    }
}
