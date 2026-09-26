<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Intake\Enums\IntakePurpose;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Resources\Models\Resource;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KnowledgeItem> */
final class KnowledgeItemFactory extends Factory
{
    protected $model = KnowledgeItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_type' => 'none',
            'resource_id' => null,
            'source_url' => null,
            'title' => 'Knowledge item '.fake()->unique()->numberBetween(1, 999999),
            'summary' => null,
            'authors' => null,
            'published_year' => null,
            'venue' => null,
            'doi' => null,
            'version' => 1,
        ];
    }

    public function linkSource(string $url = 'https://example.edu/papers/attention.pdf'): static
    {
        return $this->state(fn (): array => [
            'source_type' => 'link',
            'source_url' => $url,
            'resource_id' => null,
        ]);
    }

    public function resourceSource(Resource $resource): static
    {
        return $this->state(fn (): array => [
            'user_id' => $resource->user_id,
            'source_type' => 'resource',
            'resource_id' => $resource->getKey(),
            'source_url' => null,
        ]);
    }

    public function cited(): static
    {
        return $this->state(fn (): array => [
            'authors' => 'Vaswani, A. and Shazeer, N.',
            'published_year' => 2017,
            'venue' => 'NeurIPS',
            'doi' => '10.48550/arXiv.1706.03762',
        ]);
    }

    /**
     * Links the item to the intake capture it came from. The owner is copied
     * from the intake item because the FK is composite:
     * (user_id, intake_item_id) -> intake_items(user_id, id). Setting the two
     * independently violates it.
     */
    public function fromIntake(IntakeItem $item): static
    {
        return $this->state(fn (): array => [
            'user_id' => $item->user_id,
            'intake_item_id' => $item->getKey(),
        ]);
    }

    public function purposed(IntakePurpose $purpose): static
    {
        return $this->state(fn (): array => ['purpose' => $purpose->value]);
    }
}
