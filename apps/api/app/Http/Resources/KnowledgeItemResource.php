<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Resources\Models\Resource;
use App\Domains\SecondBrain\Enums\KnowledgePurpose;
use App\Domains\SecondBrain\Models\Collection;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\KnowledgeLink;
use App\Domains\SecondBrain\Models\KnowledgeNote;
use App\Domains\SecondBrain\Models\KnowledgeTag;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KnowledgeItem */
final class KnowledgeItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $resource = $this->relationLoaded('resource') ? $this->getRelation('resource') : null;
        $intakeItem = $this->relationLoaded('intakeItem') ? $this->getRelation('intakeItem') : null;

        $payload = [
            'id' => (string) $this->public_id,
            'version' => $this->version,
            'title' => $this->title,
            'summary' => $this->summary,
            // Null is a real value here: rows captured before purposes existed
            // have no purpose, which is not the same as any particular one.
            'purpose' => $this->purposeValue(),
            // The raw saved_at timestamp stays hidden; the surface only needs to
            // know whether the item is bookmarked, always as a present boolean.
            'saved' => $this->getAttribute('saved_at') !== null,
            'source' => [
                'type' => $this->source_type,
                'url' => $this->source_url,
                'resource' => $resource instanceof Resource ? [
                    'id' => (string) $resource->public_id,
                    'title' => (string) $resource->title,
                    'type' => $resource->kind->value,
                ] : null,
                /* The capture this item came from, when there was one. It is
                   how a deep-linked workspace resolves the extracted text to
                   render, since link captures never produce a Resource and so
                   have no other join path back to their content. The internal
                   id stays hidden; only the public id is exposed. */
                'intake_item_id' => $intakeItem instanceof IntakeItem
                    ? (string) $intakeItem->public_id
                    : null,
            ],
            'citation' => [
                'authors' => $this->authors,
                'published_year' => $this->published_year,
                'venue' => $this->venue,
                'doi' => $this->doi,
            ],
            'tags' => $this->tagNames(),
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
            'updated_at' => $this->timestamp($this->getAttribute('updated_at')),
        ];

        if ($this->relationLoaded('notes')) {
            $payload['notes'] = KnowledgeNoteResource::collection(
                $this->getRelation('notes')->filter(
                    static fn (mixed $note): bool => $note instanceof KnowledgeNote,
                )->values(),
            )->resolve($request);
        }

        if ($this->relationLoaded('collections')) {
            $payload['collections'] = $this->collectionSummaries();
        }

        if ($this->relationLoaded('outgoingLinks') && $this->relationLoaded('incomingLinks')) {
            $payload['links'] = $this->linkSummaries();
        }

        return $payload;
    }

    private function purposeValue(): ?string
    {
        $purpose = $this->getAttribute('purpose');

        return $purpose instanceof KnowledgePurpose ? $purpose->value : null;
    }

    /** @return list<string> */
    private function tagNames(): array
    {
        if (! $this->relationLoaded('tags')) {
            return [];
        }

        $names = [];
        foreach ($this->getRelation('tags') as $tag) {
            if ($tag instanceof KnowledgeTag) {
                $names[] = (string) $tag->name;
            }
        }

        return $names;
    }

    /** @return list<array{id: string, name: string, kind: string}> */
    private function collectionSummaries(): array
    {
        $summaries = [];
        foreach ($this->getRelation('collections') as $collection) {
            if ($collection instanceof Collection) {
                $summaries[] = [
                    'id' => (string) $collection->public_id,
                    'name' => (string) $collection->name,
                    'kind' => (string) $collection->kind,
                ];
            }
        }

        return $summaries;
    }

    /** @return list<array<string, mixed>> */
    private function linkSummaries(): array
    {
        $summaries = [];

        foreach ($this->getRelation('outgoingLinks') as $link) {
            if ($link instanceof KnowledgeLink && $link->toItem instanceof KnowledgeItem) {
                $summaries[] = [
                    'id' => (string) $link->public_id,
                    'direction' => 'outgoing',
                    'relation_type' => (string) $link->relation_type,
                    'item' => [
                        'id' => (string) $link->toItem->public_id,
                        'title' => (string) $link->toItem->title,
                    ],
                ];
            }
        }

        foreach ($this->getRelation('incomingLinks') as $link) {
            if ($link instanceof KnowledgeLink && $link->fromItem instanceof KnowledgeItem) {
                $summaries[] = [
                    'id' => (string) $link->public_id,
                    'direction' => 'incoming',
                    'relation_type' => (string) $link->relation_type,
                    'item' => [
                        'id' => (string) $link->fromItem->public_id,
                        'title' => (string) $link->fromItem->title,
                    ],
                ];
            }
        }

        return $summaries;
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
