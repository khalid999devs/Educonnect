<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\KnowledgeTag;
use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SyncKnowledgeItemTagsAction
{
    public function __construct(private FindOwnedKnowledgeItem $items) {}

    /** @param list<string> $names */
    public function execute(User $user, string $itemPublicId, array $names): KnowledgeItem
    {
        try {
            return DB::transaction(function () use ($user, $itemPublicId, $names): KnowledgeItem {
                $item = $this->items->execute($user, $itemPublicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $item);

                $tagIds = [];
                foreach ($this->uniqueNames($names) as $name) {
                    $tagIds[] = (int) $this->resolveTag($user, $name)->getKey();
                }

                DB::table('knowledge_item_tags')
                    ->where('knowledge_item_id', $item->getKey())
                    ->when(
                        $tagIds !== [],
                        static fn ($query) => $query->whereNotIn('knowledge_tag_id', $tagIds),
                    )
                    ->delete();

                $existing = DB::table('knowledge_item_tags')
                    ->where('knowledge_item_id', $item->getKey())
                    ->pluck('knowledge_tag_id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                foreach (array_diff($tagIds, $existing) as $tagId) {
                    DB::table('knowledge_item_tags')->insert([
                        'user_id' => $user->getKey(),
                        'knowledge_item_id' => $item->getKey(),
                        'knowledge_tag_id' => $tagId,
                    ]);
                }

                return $item->refresh()->load([
                    'resource',
                    'tags' => static fn ($tags) => $tags->orderBy('name'),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.tags.sync');
        }
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function uniqueNames(array $names): array
    {
        $unique = [];
        foreach ($names as $name) {
            $unique[mb_strtolower($name)] ??= $name;
        }

        return array_values($unique);
    }

    private function resolveTag(User $user, string $name): KnowledgeTag
    {
        $tag = KnowledgeTag::query()
            ->where('user_id', $user->getKey())
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($tag instanceof KnowledgeTag) {
            return $tag;
        }

        $tag = new KnowledgeTag;
        $tag->forceFill([
            'user_id' => $user->getKey(),
            'name' => $name,
        ])->save();

        return $tag;
    }
}
