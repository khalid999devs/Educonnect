<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MentorProfile> */
final class MentorProfileFactory extends Factory
{
    protected $model = MentorProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'headline' => 'Computer science mentor',
            'bio' => 'I help students with algorithms, study skills, and research habits.',
            'expertise' => ['algorithms', 'study skills'],
            'availability_note' => 'Usually replies within a few days.',
            'verification_state' => 'unverified',
            'is_accepting_requests' => true,
            'version' => 1,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => ['verification_state' => 'verified']);
    }

    public function notAccepting(): static
    {
        return $this->state(fn (): array => ['is_accepting_requests' => false]);
    }
}
