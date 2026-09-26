<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Models\Course;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Course> */
final class CourseFactory extends Factory
{
    protected $model = Course::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'academic_term_id' => null,
            'title' => fake()->randomElement([
                'Data Structures',
                'Software Engineering',
                'Database Systems',
            ]),
            'code' => strtoupper(fake()->bothify('??? ###')),
            'description' => null,
            'version' => 1,
            'onboarding_position' => null,
            'archived_at' => null,
        ];
    }

    public function forAcademicTerm(AcademicTerm $term): static
    {
        return $this->state(fn (): array => [
            'user_id' => $term->user_id,
            'academic_term_id' => $term->getKey(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }

    public function fromOnboardingPosition(int $position): static
    {
        return $this->state(fn (): array => ['onboarding_position' => $position]);
    }
}
