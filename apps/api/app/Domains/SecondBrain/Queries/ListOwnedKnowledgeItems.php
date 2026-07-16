<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\SecondBrain\Data\BrainListResult;
use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Support\BrainCursorSort;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ListOwnedKnowledgeItems
{
    public function __construct(
        private readonly FindOwnedCollection $collections,
        private readonly FindOwnedResearchTopic $topics,
    ) {}

    /** @return BrainListResult<KnowledgeItem> */
    public function execute(
        User $user,
        ?string $search,
        ?string $collectionPublicId,
        ?string $topicPublicId,
        ?string $tag,
        ?string $sourceType,
        string $sort,
        int $perPage,
    ): BrainListResult {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);
        $ownsTransaction = DB::transactionLevel() === 0;

        try {
            return DB::transaction(function () use (
                $user,
                $search,
                $collectionPublicId,
                $topicPublicId,
                $tag,
                $sourceType,
                $sort,
                $perPage,
                $ownsTransaction,
            ): BrainListResult {
                if ($ownsTransaction) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }

                $collection = $collectionPublicId === null
                    ? null
                    : $this->collections->execute($user, $collectionPublicId);
                $topic = $topicPublicId === null
                    ? null
                    : $this->topics->execute($user, $topicPublicId);
                $sortDefinition = BrainCursorSort::resolve($sort);
                $query = KnowledgeItem::query()
                    ->select([
                        'knowledge_items.*',
                        'knowledge_items.'.$sortDefinition['column'].' as '.$sortDefinition['cursor_column'],
                    ])
                    ->where('knowledge_items.user_id', $user->getKey())
                    ->with(['resource', 'tags' => static fn ($tags) => $tags->orderBy('name')]);

                if ($collection !== null) {
                    $query->whereExists(static function ($membership) use ($collection): void {
                        $membership->selectRaw('1')
                            ->from('collection_knowledge_items')
                            ->whereColumn('collection_knowledge_items.knowledge_item_id', 'knowledge_items.id')
                            ->where('collection_knowledge_items.collection_id', $collection->getKey());
                    });
                }

                if ($topic !== null) {
                    $query->whereExists(static function ($membership) use ($topic): void {
                        $membership->selectRaw('1')
                            ->from('research_topic_sources')
                            ->whereColumn('research_topic_sources.knowledge_item_id', 'knowledge_items.id')
                            ->where('research_topic_sources.research_topic_id', $topic->getKey());
                    });
                }

                if ($tag !== null) {
                    $loweredTag = mb_strtolower($tag);
                    $query->whereExists(static function ($assignment) use ($loweredTag): void {
                        $assignment->selectRaw('1')
                            ->from('knowledge_item_tags')
                            ->join('knowledge_tags', 'knowledge_tags.id', '=', 'knowledge_item_tags.knowledge_tag_id')
                            ->whereColumn('knowledge_item_tags.knowledge_item_id', 'knowledge_items.id')
                            ->whereRaw('LOWER(knowledge_tags.name) = ?', [$loweredTag]);
                    });
                }

                if ($sourceType !== null) {
                    $query->where('source_type', $sourceType);
                }

                if ($search !== null) {
                    $prefixPattern = BrainSearchPattern::prefix($search);
                    $query->where(static function ($matches) use ($search, $prefixPattern): void {
                        $matches->whereRaw(
                            "search_vector @@ websearch_to_tsquery('simple', ?)",
                            [$search],
                        )->orWhereRaw("LOWER(title) LIKE ? ESCAPE '\\'", [$prefixPattern]);
                    });
                }

                $paginator = $query
                    ->orderBy($sortDefinition['cursor_column'], $sortDefinition['direction'])
                    ->orderBy('public_id', $sortDefinition['direction'])
                    ->cursorPaginate($perPage)
                    ->withQueryString();
                $total = DB::table('knowledge_items')->where('user_id', $user->getKey())->count();

                return new BrainListResult($paginator, ['total' => $total]);
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.list');
        }
    }
}
