<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Courses\Models\Course;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\Users\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FocusSession> */
final class FocusSessionFactory extends Factory
{
    protected $model = FocusSession::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startsAt = now()->subHour();
        $endsAt = $startsAt->copy()->addMinutes(50);

        return [
            'user_id' => User::factory(),
            'task_id' => null,
            'course_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'note' => null,
            'version' => 1,
        ];
    }

    public function forTask(Task $task): static
    {
        return $this->state(fn (): array => [
            'user_id' => $task->user_id,
            'task_id' => $task->getKey(),
            'course_id' => null,
        ]);
    }

    public function forCourse(Course $course): static
    {
        return $this->state(fn (): array => [
            'user_id' => $course->user_id,
            'task_id' => null,
            'course_id' => $course->getKey(),
        ]);
    }

    public function between(DateTimeInterface $startsAt, DateTimeInterface $endsAt): static
    {
        return $this->state(fn (): array => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
