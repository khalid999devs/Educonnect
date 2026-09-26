<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Community\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Community> */
final class CommunityFactory extends Factory
{
    protected $model = Community::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999999);

        return [
            'slug' => 'community-'.$number,
            'name' => 'Community '.$number,
            'summary' => 'A curated space for students to learn together.',
            'description' => null,
            'topic' => null,
            'visibility' => 'published',
            'is_seeded' => false,
            'version' => 1,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['visibility' => 'archived']);
    }

    public function seeded(): static
    {
        return $this->state(fn (): array => ['is_seeded' => true]);
    }
}
