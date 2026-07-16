<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\SecondBrain\Queries\FindOwnedResearchTopic;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class DetachResearchSourceAction
{
    public function __construct(
        private FindOwnedResearchTopic $topics,
        private FindOwnedKnowledgeItem $items,
    ) {}

    public function execute(User $user, string $topicPublicId, string $itemPublicId): void
    {
        try {
            DB::transaction(function () use ($user, $topicPublicId, $itemPublicId): void {
                $topic = $this->topics->execute($user, $topicPublicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $topic);
                $item = $this->items->execute($user, $itemPublicId);

                $deleted = DB::table('research_topic_sources')
                    ->where('research_topic_id', $topic->getKey())
                    ->where('knowledge_item_id', $item->getKey())
                    ->delete();

                if ($deleted === 0) {
                    throw new NotFoundHttpException;
                }
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'research.source.detach');
        }
    }
}
