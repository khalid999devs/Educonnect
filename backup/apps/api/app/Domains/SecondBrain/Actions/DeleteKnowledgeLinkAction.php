<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\KnowledgeLink;
use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class DeleteKnowledgeLinkAction
{
    public function __construct(private FindOwnedKnowledgeItem $items) {}

    public function execute(User $user, string $itemPublicId, string $linkPublicId): void
    {
        try {
            DB::transaction(function () use ($user, $itemPublicId, $linkPublicId): void {
                $item = $this->items->execute($user, $itemPublicId);
                Gate::forUser($user)->authorize('update', $item);
                $link = KnowledgeLink::query()
                    ->where('from_item_id', $item->getKey())
                    ->where('public_id', $linkPublicId)
                    ->lockForUpdate()
                    ->first();

                if (! $link instanceof KnowledgeLink) {
                    throw new NotFoundHttpException;
                }

                $link->delete();
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.link.delete');
        }
    }
}
