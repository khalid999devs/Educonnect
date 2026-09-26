<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Models\ContentReport;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Community\Concerns\InteractsWithCommunity;
use Tests\TestCase;

final class CommunityModerationTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_member_reports_a_post_once(): void
    {
        [$community, $post] = $this->communityWithPost();
        $reporter = User::factory()->create();
        $this->actingAs($reporter, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/posts/{$post->public_id}/reports", [
                'reason' => 'spam',
                'detail' => 'This looks like an advertisement.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.reason', 'spam')
            ->assertJsonPath('data.subject.type', 'post');

        $duplicate = $this->withHeaders($this->headers())
            ->postJson("/api/v1/posts/{$post->public_id}/reports", ['reason' => 'harassment']);
        $this->assertApiError($duplicate, 409, ApiErrorCode::Conflict);
    }

    public function test_scoped_moderator_resolves_report_and_hides_content(): void
    {
        [$community, $post] = $this->communityWithPost();
        $reporter = User::factory()->create();
        $report = ContentReport::factory()->forPost($post)->create(['reporter_id' => $reporter->getKey()]);

        $moderator = User::factory()->withRole(RoleKey::Moderator)->create();
        CommunityMembership::factory()->moderator()->create([
            'community_id' => $community->getKey(),
            'user_id' => $moderator->getKey(),
        ]);
        $this->actingAs($moderator, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/moderation/reports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $report->public_id);

        $this->withHeaders($this->headers())
            ->patchJson("/api/v1/moderation/reports/{$report->public_id}/resolution", [
                'resolution' => 'actioned',
                'hide_content' => true,
                'note' => 'Removed for spam.',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'actioned');

        // The reported post is now hidden and its body withheld.
        $this->withHeaders($this->headers())
            ->getJson("/api/v1/posts/{$post->public_id}")
            ->assertOk()
            ->assertJsonPath('data.moderation_state', 'hidden_by_moderator')
            ->assertJsonPath('data.body', null);
    }

    public function test_non_moderator_cannot_reach_the_moderation_queue(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/moderation/reports')
            ->assertForbidden();
    }

    public function test_moderator_outside_scope_cannot_resolve(): void
    {
        [$community, $post] = $this->communityWithPost();
        $reporter = User::factory()->create();
        $report = ContentReport::factory()->forPost($post)->create(['reporter_id' => $reporter->getKey()]);

        // A moderator with no moderating membership in this community.
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create();
        $this->actingAs($moderator, 'web');

        $denied = $this->withHeaders($this->headers())
            ->patchJson("/api/v1/moderation/reports/{$report->public_id}/resolution", [
                'resolution' => 'dismissed',
                'expected_version' => 1,
            ]);
        $this->assertApiError($denied, 403, ApiErrorCode::AuthorizationDenied);
    }

    /** @return array{0: Community, 1: CommunityPost} */
    private function communityWithPost(): array
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

        return [$community, $post];
    }
}
