<?php

declare(strict_types=1);

namespace Tests\Feature\Mentor;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Mentor\Models\MentorRequest;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Feature\Community\Concerns\InteractsWithCommunity;
use Tests\TestCase;

final class MentorOpenApiContractTest extends TestCase
{
    use InteractsWithCommunity;
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_mentor_profile_operations_match_the_live_openapi_contract(): void
    {
        $mentor = User::factory()->withRole(RoleKey::Mentor)->create(['name' => 'Dr Contract Mentor']);
        $this->actingAs($mentor, 'web');

        $profileId = $this->withHeaders($this->headers())
            ->postJson('/api/v1/mentor-profile', [
                'headline' => 'Algorithms and study skills mentor',
                'bio' => 'Ten years tutoring computer science students.',
                'expertise' => ['algorithms', 'study skills'],
                'availability_note' => 'Replies within a few days.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.verification_state', 'unverified')
            ->assertJsonPath('data.name', 'Dr Contract Mentor')
            ->json('data.id');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/mentors?search=algorithms&expertise=algorithms&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/mentors/{$profileId}")
            ->assertOk()
            ->assertJsonPath('data.id', $profileId);

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/mentor-profile')
            ->assertOk()
            ->assertJsonPath('data.id', $profileId);

        $this->withHeaders($this->headers())
            ->patchJson('/api/v1/mentor-profile', [
                'headline' => 'Algorithms, study skills, and research mentor',
                'bio' => 'Ten years tutoring computer science students.',
                'expertise' => ['algorithms', 'research'],
                'is_accepting_requests' => true,
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);
    }

    public function test_student_request_operations_match_the_live_openapi_contract(): void
    {
        $mentorUser = User::factory()->withRole(RoleKey::Mentor)->create();
        $profile = MentorProfile::factory()->create(['user_id' => $mentorUser->getKey()]);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/mentors/{$profile->public_id}/requests", [
                'subject' => 'Dynamic programming help',
                'message' => 'Could you review my memoisation approach?',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/mentor-requests?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_mentor_incoming_operations_match_the_live_openapi_contract(): void
    {
        $mentorUser = User::factory()->withRole(RoleKey::Mentor)->create();
        $profile = MentorProfile::factory()->create(['user_id' => $mentorUser->getKey()]);
        $requester = User::factory()->create();
        $request = MentorRequest::factory()->create([
            'requester_id' => $requester->getKey(),
            'mentor_profile_id' => $profile->getKey(),
        ]);

        $this->actingAs($mentorUser, 'web');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/mentor-requests/incoming?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->headers())
            ->patchJson("/api/v1/mentor-requests/{$request->public_id}", [
                'action' => 'accept',
                'response_note' => 'Happy to help. Send your code.',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    /** @return array<string, string> */
    private function readHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'mentor-contract-session');

        return $authenticatedRequest;
    }
}
