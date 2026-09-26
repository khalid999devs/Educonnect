<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Courses\Models\Course;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Domains\Resources\Models\Resource> */
final class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => null,
            'kind' => ResourceKind::Link->value,
            'title' => fake()->randomElement([
                'Course reference',
                'Lecture companion',
                'Study guide',
            ]),
            'description' => null,
            'topic_label' => null,
            'source_url' => 'https://example.edu/resources/'.fake()->unique()->slug(3),
            'version' => 1,
        ];
    }

    public function link(?string $sourceUrl = null): static
    {
        return $this->state(fn (): array => [
            'kind' => ResourceKind::Link->value,
            'source_url' => $sourceUrl ?? 'https://example.edu/resources/'.fake()->unique()->slug(3),
        ]);
    }

    public function file(): static
    {
        return $this->state(fn (): array => [
            'kind' => ResourceKind::File->value,
            'source_url' => null,
        ]);
    }

    public function forCourse(Course $course): static
    {
        return $this->state(fn (): array => [
            'user_id' => $course->user_id,
            'course_id' => $course->getKey(),
        ]);
    }

    public function forTopic(string $topic): static
    {
        return $this->state(fn (): array => ['topic_label' => $topic]);
    }
}
