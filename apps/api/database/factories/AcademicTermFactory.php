<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AcademicTerm> */
final class AcademicTermFactory extends Factory
{
    protected $model = AcademicTerm::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Spring 2026', 'Summer 2026', 'Fall 2026']),
            'starts_on' => null,
            'ends_on' => null,
            'version' => 1,
        ];
    }

    public function withDates(): static
    {
        $startsOn = fake()->dateTimeBetween('-1 year', '+1 year');

        return $this->state(fn (): array => [
            'starts_on' => $startsOn,
            'ends_on' => (clone $startsOn)->modify('+4 months'),
        ]);
    }
}
