<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\Resources\Queries\FindOwnedResource;
use App\Domains\SecondBrain\Enums\KnowledgeSourceType;
use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class CreateKnowledgeItemAction
{
    public function __construct(private FindOwnedResource $resources) {}

    /**
     * @param array{
     *     title: string,
     *     summary: ?string,
     *     source_type: string,
     *     resource_id: ?string,
     *     source_url: ?string,
     *     authors: ?string,
     *     published_year: ?int,
     *     venue: ?string,
     *     doi: ?string,
     * } $data
     */
    public function execute(User $user, array $data): KnowledgeItem
    {
        Gate::forUser($user)->authorize('create', KnowledgeItem::class);

        try {
            return DB::transaction(function () use ($user, $data): KnowledgeItem {
                $resource = $data['source_type'] === KnowledgeSourceType::Resource->value
                    ? $this->resources->execute($user, (string) $data['resource_id'])
                    : null;

                $item = new KnowledgeItem;
                $item->forceFill([
                    'user_id' => $user->getKey(),
                    'source_type' => $data['source_type'],
                    'resource_id' => $resource?->getKey(),
                    'source_url' => $data['source_type'] === KnowledgeSourceType::Link->value
                        ? $data['source_url']
                        : null,
                    'title' => $data['title'],
                    'summary' => $data['summary'],
                    'authors' => $data['authors'],
                    'published_year' => $data['published_year'],
                    'venue' => $data['venue'],
                    'doi' => $data['doi'],
                    'version' => 1,
                ])->save();

                return $item->load(['resource', 'tags']);
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.create');
        }
    }
}
