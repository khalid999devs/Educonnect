<?php

declare(strict_types=1);

namespace Tests\Feature\Mentor;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Mentor\Enums\MentorRequestStatus;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Mentor\Models\MentorRequest;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Community\Concerns\InteractsWithCommunity;
use Tests\TestCase;

/**
 * The Mentors surface is hidden until a student holds a real mentor connection.
 * The signal is GET /mentor-requests?status=accepted,completed: a completed
 * mentorship still counts, because finishing an engagement must never take a
 * student's mentorship history away from them.
 */
final class MentorConnectionGateTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_accepted_filter_returns_only_accepted_requests(): void
    {
        $student = User::factory()->create();
        $this->seedRequest($student, MentorRequestStatus::Open);
        $this->seedRequest($student, MentorRequestStatus::Declined);
        $accepted = $this->seedRequest($student, MentorRequestStatus::Accepted);

        $this->actingAs($student, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/mentor-requests?status=accepted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $accepted->public_id)
            ->assertJsonPath('data.0.status', 'accepted')
            ->assertJsonPath('meta.summary.total', 1);
    }

    public function test_completed_alone_satisfies_the_gate(): void
    {
        $student = User::factory()->create();
        $this->seedRequest($student, MentorRequestStatus::Completed);
        $this->seedRequest($student, MentorRequestStatus::Withdrawn);

        $this->actingAs($student, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/mentor-requests?status=accepted,completed&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('meta.summary.total', 1);
    }

    public function test_the_gate_counts_accepted_and_completed_together(): void
    {
        $student = User::factory()->create();
        $this->seedRequest($student, MentorRequestStatus::Accepted);
        $this->seedRequest($student, MentorRequestStatus::Completed);
        $this->seedRequest($student, MentorRequestStatus::Open);
        $this->seedRequest($student, MentorRequestStatus::Declined);
        $this->seedRequest($student, MentorRequestStatus::Withdrawn);

        $this->actingAs($student, 'web');

        // per_page=1 is what the nav gate sends: the meta total is the answer, the
        // page body is incidental.
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/mentor-requests?status=accepted,completed&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.summary.total', 2);

        self::assertContains($response->json('data.0.status'), ['accepted', 'completed']);
    }

    public function test_no_connection_when_only_open_declined_or_withdrawn_exist(): void
    {
        $student = User::factory()->create();
        $this->seedRequest($student, MentorRequestStatus::Open);
        $this->seedRequest($student, MentorRequestStatus::Declined);
        $this->seedRequest($student, MentorRequestStatus::Withdrawn);

        $this->actingAs($student, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/mentor-requests?status=accepted,completed&per_page=1')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.summary.total', 0);
    }

    public function test_another_students_connection_never_satisfies_the_gate(): void
    {
        $connected = User::factory()->create();
        $this->seedRequest($connected, MentorRequestStatus::Accepted);
        $this->seedRequest($connected, MentorRequestStatus::Completed);

        $stranger = User::factory()->create();
        $this->seedRequest($stranger, MentorRequestStatus::Open);

        $this->actingAs($stranger, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/mentor-requests?status=accepted,completed&per_page=1')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.summary.total', 0);
    }

    public function test_unfiltered_listing_still_returns_every_status(): void
    {
        $student = User::factory()->create();
        $this->seedRequest($student, MentorRequestStatus::Open);
        $this->seedRequest($student, MentorRequestStatus::Accepted);
        $this->seedRequest($student, MentorRequestStatus::Completed);

        $this->actingAs($student, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/mentor-requests')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.summary.total', 3);
    }

    public function test_an_unknown_status_value_is_rejected(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student, 'web');

        foreach (['bogus', 'accepted,bogus', 'accepted,', 'accepted;completed'] as $value) {
            $rejected = $this->withHeaders($this->headers())
                ->getJson('/api/v1/mentor-requests?status='.rawurlencode($value));
            $this->assertApiError($rejected, 422, ApiErrorCode::ValidationFailed);
        }
    }

    public function test_an_unknown_query_parameter_is_still_rejected(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student, 'web');

        $rejected = $this->withHeaders($this->headers())
            ->getJson('/api/v1/mentor-requests?connected=true');
        $this->assertApiError($rejected, 422, ApiErrorCode::ValidationFailed);
    }

    public function test_incoming_requests_honour_the_same_status_filter(): void
    {
        $mentorUser = User::factory()->withRole(RoleKey::Mentor)->create();
        $profile = MentorProfile::factory()->create(['user_id' => $mentorUser->getKey()]);

        foreach ([MentorRequestStatus::Open, MentorRequestStatus::Accepted] as $status) {
            MentorRequest::factory()->withStatus($status)->create([
                'requester_id' => User::factory()->create()->getKey(),
                'mentor_profile_id' => $profile->getKey(),
            ]);
        }

        $this->actingAs($mentorUser, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/mentor-requests/incoming?status=accepted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'accepted')
            // The open badge count stays unfiltered on purpose.
            ->assertJsonPath('meta.summary.open', 1);
    }

    private function seedRequest(User $requester, MentorRequestStatus $status): MentorRequest
    {
        $mentorUser = User::factory()->withRole(RoleKey::Mentor)->create();
        $profile = MentorProfile::factory()->create(['user_id' => $mentorUser->getKey()]);

        return MentorRequest::factory()->withStatus($status)->create([
            'requester_id' => $requester->getKey(),
            'mentor_profile_id' => $profile->getKey(),
        ]);
    }
}
