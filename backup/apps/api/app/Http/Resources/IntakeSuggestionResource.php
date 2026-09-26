<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Intake\Models\IntakeSuggestion;
use App\Domains\Planner\Models\Task;
use App\Domains\Resources\Models\Resource;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin IntakeSuggestion */
final class IntakeSuggestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $payload = $this->payload;
        $createdTask = $this->relationLoaded('createdTask') ? $this->getRelation('createdTask') : null;
        $createdResource = $this->relationLoaded('createdResource') ? $this->getRelation('createdResource') : null;
        $createdKnowledgeItem = $this->relationLoaded('createdKnowledgeItem')
            ? $this->getRelation('createdKnowledgeItem')
            : null;

        return [
            'id' => (string) $this->public_id,
            'kind' => $this->kind->value,
            'proposal' => [
                'title' => $this->payloadString($payload, 'title'),
                'description' => $this->payloadString($payload, 'description'),
                'due_at' => $this->payloadString($payload, 'due_at'),
                'course_id' => $this->payloadString($payload, 'course_public_id'),
                'url' => $this->payloadString($payload, 'url'),
            ],
            'confidence' => round((float) $this->confidence, 3),
            'reason' => (string) $this->reason,
            'schema_version' => (string) $this->schema_version,
            'status' => $this->status->value,
            'created_task_id' => $createdTask instanceof Task ? (string) $createdTask->public_id : null,
            'created_resource_id' => $createdResource instanceof Resource ? (string) $createdResource->public_id : null,
            'created_knowledge_item_id' => $createdKnowledgeItem instanceof KnowledgeItem
                ? (string) $createdKnowledgeItem->public_id
                : null,
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }

    /** @param array<string, mixed> $payload */
    private function payloadString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
