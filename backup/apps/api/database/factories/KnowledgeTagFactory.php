<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\SecondBrain\Models\KnowledgeTag;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KnowledgeTag> */
final class KnowledgeTagFactory extends Factory
{
    protected $model = KnowledgeTag::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'tag-'.fake()->unique()->numberBetween(1, 999999),
        ];
    }
}
