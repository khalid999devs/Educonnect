<?php

declare(strict_types=1);

namespace Database\Factories;

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
}
