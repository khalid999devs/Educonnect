<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\ResearchTopic;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ResearchTopic */
final class ResearchTopicResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $sourceCount = $this->getAttribute('sources_count');
        $keywords = $this->getAttribute('keywords');

        $payload = [
            'id' => (string) $this->public_id,
            'version' => $this->version,
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => is_array($keywords) ? array_values($keywords) : [],
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
            'updated_at' => $this->timestamp($this->getAttribute('updated_at')),
        ];

        if ($this->relationLoaded('sources')) {
            $payload['sources'] = $this->sourceSummaries();
            $payload['source_count'] = count($payload['sources']);
        } else {
            $payload['source_count'] = is_numeric($sourceCount) ? (int) $sourceCount : 0;
        }

        return $payload;
    }

    /** @return list<array<string, mixed>> */
    private function sourceSummaries(): array
    {
        $summaries = [];

        foreach ($this->getRelation('sources') as $item) {
            if (! $item instanceof KnowledgeItem) {
                continue;
            }

            $readingStatus = $item->getRelationValue('pivot')?->getAttribute('reading_status');
            $summaries[] = [
                'item' => [
                    'id' => (string) $item->public_id,
                    'title' => (string) $item->title,
                    'source_type' => (string) $item->source_type,
                    'authors' => $item->authors,
                    'published_year' => $item->published_year,
                    'venue' => $item->venue,
                    'doi' => $item->doi,
                ],
                'reading_status' => is_string($readingStatus) ? $readingStatus : null,
            ];
        }

        return $summaries;
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
