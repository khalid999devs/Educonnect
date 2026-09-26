<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Mentor\Enums\MentorRequestStatus;
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
            'status' => MentorRequestStatus::Open->value,
            'responded_at' => null,
            'response_note' => null,
            'version' => 1,
        ];
    }

    /**
     * `mentor_requests_responded_consistent` requires responded_at to be NULL
     * for an open request and set for every other status, so the timestamp
     * always moves with the status rather than being remembered separately.
     */
    public function withStatus(MentorRequestStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status->value,
            'responded_at' => $status === MentorRequestStatus::Open ? null : now('UTC'),
        ]);
    }
}
