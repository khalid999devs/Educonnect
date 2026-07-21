<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Study\Enums\StudyArtifactKind;
use App\Domains\Study\Enums\StudyArtifactStatus;
use App\Domains\Study\Models\StudyArtifact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Every state helper here respects the table's CHECK constraints, because the
 * database refuses the alternative:
 * - `payload` is legal ONLY on `ready`;
 * - `failure_reason` is legal ONLY on `failed`;
 * - `UNIQUE(knowledge_item_id, kind)` allows one artifact per material per kind.
 *
 * `user_id` is derived from the knowledge item rather than generated
 * independently, because the FK is composite:
 * (user_id, knowledge_item_id) -> knowledge_items(user_id, id).
 *
 * @extends Factory<StudyArtifact>
 */
final class StudyArtifactFactory extends Factory
{
    protected $model = StudyArtifact::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'knowledge_item_id' => KnowledgeItem::factory(),
            'user_id' => static fn (array $attributes): mixed => KnowledgeItem::query()
                ->whereKey($attributes['knowledge_item_id'])
                ->value('user_id'),
            'kind' => StudyArtifactKind::Summary->value,
            'status' => StudyArtifactStatus::Queued->value,
            'payload' => null,
            'schema_version' => 'v1',
            'provider' => null,
            'model' => null,
            'latency_ms' => null,
            'failure_reason' => null,
            'version' => 1,
        ];
    }

    /** Pins both halves of the composite foreign key to one owned material. */
    public function forItem(KnowledgeItem $item): static
    {
        return $this->state(fn (): array => [
            'knowledge_item_id' => $item->getKey(),
            'user_id' => $item->user_id,
        ]);
    }

    public function kind(StudyArtifactKind $kind): static
    {
        return $this->state(fn (): array => ['kind' => $kind->value]);
    }

    public function queued(): static
    {
        return $this->state(fn (): array => [
            'status' => StudyArtifactStatus::Queued->value,
            'payload' => null,
            'failure_reason' => null,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => StudyArtifactStatus::Running->value,
            'payload' => null,
            'failure_reason' => null,
        ]);
    }

    /** @param array<string, mixed>|null $payload */
    public function ready(?array $payload = null): static
    {
        return $this->state(fn (): array => [
            'status' => StudyArtifactStatus::Ready->value,
            'payload' => $payload ?? [
                'title' => 'Photosynthesis in C4 plants',
                'overview' => 'The chapter covers the light reactions and the carbon-fixation pathway.',
                'sections' => [
                    ['heading' => 'Light reactions', 'body' => 'Photosystems II and I move electrons along the thylakoid chain.'],
                ],
                'key_points' => ['C4 plants concentrate carbon dioxide before fixation.'],
            ],
            'failure_reason' => null,
            'provider' => 'openai',
            'model' => 'test-model',
            'latency_ms' => 1200,
        ]);
    }

    public function failed(string $reason = 'The AI provider was unavailable.'): static
    {
        return $this->state(fn (): array => [
            'status' => StudyArtifactStatus::Failed->value,
            'payload' => null,
            'failure_reason' => $reason,
        ]);
    }
}
