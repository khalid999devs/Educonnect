<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Queries\FindOwnedCollection;
use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SyncKnowledgeItemCollectionsAction
{
    public function __construct(
        private FindOwnedKnowledgeItem $items,
        private FindOwnedCollection $collections,
    ) {}

    /** @param list<string> $collectionPublicIds */
    public function execute(User $user, string $itemPublicId, array $collectionPublicIds): KnowledgeItem
    {
        try {
            return DB::transaction(function () use ($user, $itemPublicId, $collectionPublicIds): KnowledgeItem {
                $item = $this->items->execute($user, $itemPublicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $item);

                $collectionIds = [];
                foreach (array_values(array_unique($collectionPublicIds)) as $publicId) {
                    $collectionIds[] = (int) $this->collections->execute($user, $publicId)->getKey();
                }

                DB::table('collection_knowledge_items')
                    ->where('knowledge_item_id', $item->getKey())
                    ->when(
                        $collectionIds !== [],
                        static fn ($query) => $query->whereNotIn('collection_id', $collectionIds),
                    )
                    ->delete();

                $existing = DB::table('collection_knowledge_items')
                    ->where('knowledge_item_id', $item->getKey())
                    ->pluck('collection_id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                foreach (array_diff($collectionIds, $existing) as $collectionId) {
                    DB::table('collection_knowledge_items')->insert([
                        'user_id' => $user->getKey(),
                        'collection_id' => $collectionId,
                        'knowledge_item_id' => $item->getKey(),
                    ]);
                }

                return $item->refresh()->load([
                    'resource',
                    'tags' => static fn ($tags) => $tags->orderBy('name'),
                    'collections' => static fn ($collections) => $collections->orderBy('name'),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.collections.sync');
        }
    }
}
