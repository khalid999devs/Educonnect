<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Tools\Models\ToolCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ToolCategory> */
final class ToolCategoryFactory extends Factory
{
    protected $model = ToolCategory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = 'Tool Category '.fake()->unique()->numerify('########');

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'description' => 'Curated tools for '.Str::lower($name).'.',
            'sort_order' => fake()->numberBetween(0, 1000),
        ];
    }
}
