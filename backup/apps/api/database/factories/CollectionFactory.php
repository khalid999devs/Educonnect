<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\SecondBrain\Models\Collection;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Collection> */
final class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Collection '.fake()->unique()->numberBetween(1, 999999),
            'description' => null,
            'kind' => 'general',
            'version' => 1,
        ];
    }

    public function kind(string $kind): static
    {
        return $this->state(fn (): array => ['kind' => $kind]);
    }
}
