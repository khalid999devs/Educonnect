<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Courses\Models\Course;
use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Planner\Models\Task;
use App\Domains\Users\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
final class TaskFactory extends Factory
{
    protected $model = Task::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => null,
            'title' => fake()->randomElement([
                'Finish the problem set',
                'Review lecture notes',
                'Prepare the project outline',
            ]),
            'description' => null,
            'due_at' => null,
            'status' => TaskStatus::Pending->value,
            'completed_at' => null,
            'version' => 1,
            'archived_at' => null,
        ];
    }

    public function forCourse(Course $course): static
    {
        return $this->state(fn (): array => [
            'user_id' => $course->user_id,
            'course_id' => $course->getKey(),
        ]);
    }

    public function dueAt(DateTimeInterface $dueAt): static
    {
        return $this->state(fn (): array => ['due_at' => $dueAt]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::InProgress->value,
            'completed_at' => null,
        ]);
    }

    public function completed(?DateTimeInterface $completedAt = null): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::Completed->value,
            'completed_at' => $completedAt ?? now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
