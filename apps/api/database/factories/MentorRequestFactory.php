<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Mentor\Models\MentorRequest;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MentorRequest> */
final class MentorRequestFactory extends Factory
{
    protected $model = MentorRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'mentor_profile_id' => MentorProfile::factory(),
            'subject' => 'Help with algorithms',
            'message' => 'Could you review my approach to dynamic programming?',
            'context_course_id' => null,
            'status' => 'open',
            'response_note' => null,
            'version' => 1,
        ];
    }
}
