<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\ResearchTopic;
use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\SecondBrain\Queries\FindOwnedResearchTopic;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class UpdateResearchSourceAction
{
    public function __construct(
        private FindOwnedResearchTopic $topics,
        private FindOwnedKnowledgeItem $items,
    ) {}

    public function execute(
        User $user,
        string $topicPublicId,
        string $itemPublicId,
        string $readingStatus,
    ): ResearchTopic {
        try {
            return DB::transaction(function () use ($user, $topicPublicId, $itemPublicId, $readingStatus): ResearchTopic {
                $topic = $this->topics->execute($user, $topicPublicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $topic);
                $item = $this->items->execute($user, $itemPublicId);

                $source = DB::table('research_topic_sources')
                    ->where('research_topic_id', $topic->getKey())
                    ->where('knowledge_item_id', $item->getKey())
                    ->first();

                if ($source === null) {
                    throw new NotFoundHttpException;
                }

                if ($source->reading_status !== $readingStatus) {
                    DB::table('research_topic_sources')
                        ->where('id', $source->id)
                        ->update([
                            'reading_status' => $readingStatus,
                            'updated_at' => now('UTC'),
                        ]);
                }

                return $this->topics->execute($user, $topicPublicId, withSources: true);
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'research.source.update');
        }
    }
}
