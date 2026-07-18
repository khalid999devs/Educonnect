<?php

declare(strict_types=1);

namespace Tests\Feature\Mentor;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Mentor\Models\MentorRequest;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Community\Concerns\InteractsWithCommunity;
use Tests\TestCase;

final class MentorFlowTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_students_cannot_create_a_mentor_profile(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student, 'web');

        $denied = $this->withHeaders($this->headers())
            ->postJson('/api/v1/mentor-profile', [
                'headline' => 'Aspiring mentor',
                'bio' => 'I would like to help.',
            ]);
        $this->assertApiError($denied, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_mentor_creates_profile_and_appears_in_the_directory(): void
    {
        $mentor = User::factory()->withRole(RoleKey::Mentor)->create(['name' => 'Dr Sarah Lee']);
        $this->actingAs($mentor, 'web');

        $created = $this->withHeaders($this->headers())
            ->postJson('/api/v1/mentor-profile', [
                'headline' => 'Algorithms and study skills mentor',
                'bio' => 'Ten years tutoring computer science students.',
                'expertise' => ['algorithms', 'study skills', 'algorithms'],
                'availability_note' => 'Replies within a few days.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.verification_state', 'unverified')
            ->assertJsonPath('data.name', 'Dr Sarah Lee');
        // Duplicate expertise is de-duplicated.
        self::assertSame(['algorithms', 'study skills'], $created->json('data.expertise'));

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/mentors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.headline', 'Algorithms and study skills mentor');

        $duplicate = $this->withHeaders($this->headers())
            ->postJson('/api/v1/mentor-profile', ['headline' => 'Second', 'bio' => 'Another.']);
        $this->assertApiError($duplicate, 409, ApiErrorCode::Conflict);

        $stale = $this->withHeaders($this->headers())
            ->patchJson('/api/v1/mentor-profile', [
                'headline' => 'Updated headline',
                'bio' => 'Updated bio.',
                'expected_version' => 99,
            ]);
        $this->assertApiError($stale, 409, ApiErrorCode::Conflict);
    }

    public function test_student_requests_help_and_can_withdraw(): void
    {
        $mentorUser = User::factory()->withRole(RoleKey::Mentor)->create();
        $profile = MentorProfile::factory()->create(['user_id' => $mentorUser->getKey()]);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');

        $request = $this->withHeaders($this->headers())
            ->postJson("/api/v1/mentors/{$profile->public_id}/requests", [
                'subject' => 'Dynamic programming help',
                'message' => 'Could you review my memoisation approach?',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');
        $requestId = $request->json('data.id');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/mentor-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->headers())
            ->patchJson("/api/v1/mentor-requests/{$requestId}", [
                'action' => 'withdraw',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn');
    }

    public function test_mentor_accepts_then_completes_a_request(): void
    {
        $mentorUser = User::factory()->withRole(RoleKey::Mentor)->create();
        $profile = MentorProfile::factory()->create(['user_id' => $mentorUser->getKey()]);
        $requester = User::factory()->create();
        $request = MentorRequest::factory()->create([
            'requester_id' => $requester->getKey(),
            'mentor_profile_id' => $profile->getKey(),
        ]);

        $this->actingAs($mentorUser, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/mentor-requests/incoming')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->headers())
            ->patchJson("/api/v1/mentor-requests/{$request->public_id}", [
                'action' => 'accept',
                'response_note' => 'Happy to help — send your code.',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->withHeaders($this->headers())
            ->patchJson("/api/v1/mentor-requests/{$request->public_id}", [
                'action' => 'complete',
                'expected_version' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        // Completing again is a state conflict.
        $conflict = $this->withHeaders($this->headers())
            ->patchJson("/api/v1/mentor-requests/{$request->public_id}", [
                'action' => 'complete',
                'expected_version' => 3,
            ]);
        $this->assertApiError($conflict, 409, ApiErrorCode::Conflict);
    }

    public function test_mentor_cannot_request_help_from_their_own_profile(): void
    {
        $mentorUser = User::factory()->withRole(RoleKey::Mentor)->create();
        $profile = MentorProfile::factory()->create(['user_id' => $mentorUser->getKey()]);
        $this->actingAs($mentorUser, 'web');

        $denied = $this->withHeaders($this->headers())
            ->postJson("/api/v1/mentors/{$profile->public_id}/requests", [
                'subject' => 'Self request',
                'message' => 'Requesting help from myself.',
            ]);
        $this->assertApiError($denied, 403, ApiErrorCode::AuthorizationDenied);
    }
}
