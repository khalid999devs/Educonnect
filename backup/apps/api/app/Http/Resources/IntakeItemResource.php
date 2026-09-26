<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Intake\Enums\IntakeArtifactKind;
use App\Domains\Intake\Models\IntakeArtifact;
use App\Domains\Intake\Models\IntakeEvent;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Resources\Models\Resource;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin IntakeItem */
final class IntakeItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $resource = $this->relationLoaded('resource') ? $this->getRelation('resource') : null;

        return [
            'id' => (string) $this->public_id,
            'source' => [
                'type' => $this->source_type->value,
                'url' => $this->url,
                'resource' => $resource instanceof Resource ? [
                    'id' => (string) $resource->public_id,
                    'title' => (string) $resource->title,
                ] : null,
            ],
            'context' => $this->context,
            'state' => $this->state->value,
            'failure_code' => $this->failure_code?->value,
            'attempts' => (int) $this->attempts,
            'extraction' => $this->extractionSummary(),
            'classification' => is_string($this->classification_provider) ? [
                'provider' => $this->classification_provider,
                'model' => (string) $this->classification_model,
                'schema_version' => (string) $this->classification_schema_version,
                'latency_ms' => (int) $this->classification_latency_ms,
            ] : null,
            'events' => $this->eventSummaries(),
            'queued_at' => $this->timestamp($this->queued_at),
            'started_at' => $this->timestamp($this->started_at),
            'finished_at' => $this->timestamp($this->finished_at),
            'cancelled_at' => $this->timestamp($this->cancelled_at),
            'created_at' => $this->timestamp($this->created_at),
            'updated_at' => $this->timestamp($this->updated_at),
        ];
    }

    /** @return array{characters: int, content_type: string}|null */
    private function extractionSummary(): ?array
    {
        if (! $this->relationLoaded('artifacts')) {
            return null;
        }

        foreach ($this->getRelation('artifacts') as $artifact) {
            if ($artifact instanceof IntakeArtifact && $artifact->kind === IntakeArtifactKind::ExtractedText) {
                return [
                    'characters' => is_string($artifact->text_content) ? mb_strlen($artifact->text_content) : 0,
                    'content_type' => (string) $artifact->content_type,
                ];
            }
        }

        return null;
    }

    /** @return list<array{event: string, from_state: string|null, to_state: string|null, detail: string|null, occurred_at: string|null}> */
    private function eventSummaries(): array
    {
        if (! $this->relationLoaded('events')) {
            return [];
        }

        $summaries = [];

        foreach ($this->getRelation('events') as $event) {
            if (! $event instanceof IntakeEvent) {
                continue;
            }

            $summaries[] = [
                'event' => (string) $event->event,
                'from_state' => $event->from_state,
                'to_state' => $event->to_state,
                'detail' => $event->detail,
                'occurred_at' => $this->timestamp($event->created_at),
            ];
        }

        return array_slice($summaries, -20);
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
