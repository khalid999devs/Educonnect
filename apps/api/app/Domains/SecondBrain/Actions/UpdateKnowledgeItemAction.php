<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Exceptions\BrainVersionConflict;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateKnowledgeItemAction
{
    public function __construct(private FindOwnedKnowledgeItem $items) {}

    /**
     * @param array{
     *     title: string,
     *     summary: ?string,
     *     authors: ?string,
     *     published_year: ?int,
     *     venue: ?string,
     *     doi: ?string,
     * } $data
     */
    public function execute(User $user, string $publicId, array $data, int $expectedVersion): KnowledgeItem
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $data, $expectedVersion): KnowledgeItem {
                $item = $this->items->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $item);
                $desired = [
                    'title' => $data['title'],
                    'summary' => $data['summary'],
                    'authors' => $data['authors'],
                    'published_year' => $data['published_year'],
                    'venue' => $data['venue'],
                    'doi' => $data['doi'],
                ];

                if ($this->canonical($item) === $desired) {
                    return $item->load(['resource', 'tags']);
                }

                if ($item->version !== $expectedVersion) {
                    throw new BrainVersionConflict;
                }

                $item->forceFill([
                    ...$desired,
                    'version' => $item->version + 1,
                ])->save();

                return $item->refresh()->load(['resource', 'tags']);
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.update');
        }
    }

    /** @return array{title: string, summary: ?string, authors: ?string, published_year: ?int, venue: ?string, doi: ?string} */
    private function canonical(KnowledgeItem $item): array
    {
        return [
            'title' => $item->title,
            'summary' => $item->summary,
            'authors' => $item->authors,
            'published_year' => $item->published_year,
            'venue' => $item->venue,
            'doi' => $item->doi,
        ];
    }
}
