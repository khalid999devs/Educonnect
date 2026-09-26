<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Models\ContentReport;
use App\Domains\Mentor\Enums\MentorVerificationState;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminOpenApiContractTest extends TestCase
{
    use InteractsWithAdminApi;
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    public function test_admin_operations_match_the_live_openapi_contract(): void
    {
        $superAdmin = User::factory()->withRole(RoleKey::SuperAdmin)->create([
            'email' => 'super@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create();
        $profile = MentorProfile::factory()->create([
            'verification_state' => MentorVerificationState::Unverified->value,
            'version' => 1,
        ]);
        [$report] = $this->reportedContent();

        $this->signInAsAdmin($superAdmin);
        $this->reauthenticateAdmin();

        $this->adminGet('/api/v1/admin/users?per_page=10')->assertOk();
        $this->adminGet("/api/v1/admin/users/{$target->public_id}")->assertOk();
        $this->adminPost("/api/v1/admin/users/{$target->public_id}/suspension", [
            'reason' => 'Contract check suspension.',
        ])->assertOk();
        $this->adminPost("/api/v1/admin/users/{$target->public_id}/reactivation", [
            'reason' => 'Contract check reactivation.',
        ])->assertOk();
        $this->adminPut("/api/v1/admin/users/{$target->public_id}/roles", [
            'roles' => [RoleKey::Student->value, RoleKey::Mentor->value],
            'reason' => 'Contract check role change.',
        ])->assertOk();

        $this->adminGet('/api/v1/admin/roles')->assertOk();

        $this->adminGet('/api/v1/admin/mentors?per_page=10')->assertOk();
        $this->adminPatch("/api/v1/admin/mentors/{$profile->public_id}/verification", [
            'verification_state' => MentorVerificationState::Verified->value,
            'expected_version' => 1,
            'reason' => 'Contract check verification.',
        ])->assertOk();

        $this->adminGet('/api/v1/admin/reports?per_page=10')->assertOk();
        $this->adminPatch("/api/v1/admin/reports/{$report->public_id}/resolution", [
            'resolution' => 'actioned',
            'hide_content' => true,
            'note' => 'Removed after review.',
            'expected_version' => 1,
            'reason' => 'Contract check resolution.',
        ])->assertOk();

        $this->adminGet('/api/v1/admin/audit-events?per_page=10')->assertOk();
    }

    /** @return array{0: ContentReport} */
    private function reportedContent(): array
    {
        $community = Community::factory()->create();
        $author = User::factory()->create();
        CommunityMembership::factory()->create([
            'community_id' => $community->getKey(),
            'user_id' => $author->getKey(),
        ]);
        $post = CommunityPost::factory()->create([
            'community_id' => $community->getKey(),
            'author_id' => $author->getKey(),
        ]);
        $reporter = User::factory()->create();
        $report = ContentReport::factory()->forPost($post)->create([
            'reporter_id' => $reporter->getKey(),
        ]);

        return [$report];
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'admin-contract-session');

        return $authenticatedRequest;
    }
}
