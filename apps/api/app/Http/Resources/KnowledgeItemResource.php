<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Resources\Models\Resource;
use App\Domains\SecondBrain\Models\Collection;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\KnowledgeLink;
use App\Domains\SecondBrain\Models\KnowledgeNote;
use App\Domains\SecondBrain\Models\KnowledgeTag;
use App\Domains\SecondBrain\Models\ResearchTopic;
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

        $payload = [
            'id' => (string) $this->public_id,
            'version' => $this->version,
            'title' => $this->title,
            'summary' => $this->summary,
            'source' => [
                'type' => $this->source_type,
                'url' => $this->source_url,
                'resource' => $resource instanceof Resource ? [
                    'id' => (string) $resource->public_id,
                    'title' => (string) $resource->title,
                    'type' => $resource->kind->value,
                ] : null,
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

        if ($this->relationLoaded('researchTopics')) {
            $payload['research_topics'] = $this->topicSummaries();
        }

        return $payload;
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

    /** @return list<array{id: string, title: string, reading_status: string|null}> */
    private function topicSummaries(): array
    {
        $summaries = [];
        foreach ($this->getRelation('researchTopics') as $topic) {
            if ($topic instanceof ResearchTopic) {
                $readingStatus = $topic->getRelationValue('pivot')?->getAttribute('reading_status');
                $summaries[] = [
                    'id' => (string) $topic->public_id,
                    'title' => (string) $topic->title,
                    'reading_status' => is_string($readingStatus) ? $readingStatus : null,
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
