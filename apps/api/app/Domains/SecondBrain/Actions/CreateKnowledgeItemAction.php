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
     *     purpose: ?string,
     * } $data
     * @param  int|null  $intakeItemId  Internal id of the capture this item came
     *                                  from. Not accepted over HTTP: provenance
     *                                  is stamped by the intake pipeline, never
     *                                  claimed by a client. The composite FK
     *                                  (user_id, intake_item_id) makes a
     *                                  cross-owner link impossible at the
     *                                  database, not merely at this layer.
     */
    public function execute(User $user, array $data, ?int $intakeItemId = null): KnowledgeItem
    {
        Gate::forUser($user)->authorize('create', KnowledgeItem::class);

        try {
            return DB::transaction(function () use ($user, $data, $intakeItemId): KnowledgeItem {
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
                    'purpose' => $data['purpose'],
                    'intake_item_id' => $intakeItemId,
                    'version' => 1,
                ])->save();

                return $item->load(['resource', 'tags']);
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.create');
        }
    }
}
