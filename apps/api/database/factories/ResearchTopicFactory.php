<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\SecondBrain\Models\ResearchTopic;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResearchTopic> */
final class ResearchTopicFactory extends Factory
{
    protected $model = ResearchTopic::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Research topic '.fake()->unique()->numberBetween(1, 999999),
            'description' => null,
            'keywords' => ['transformers', 'attention'],
            'version' => 1,
        ];
    }
}
