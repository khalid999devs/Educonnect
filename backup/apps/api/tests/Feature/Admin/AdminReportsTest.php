<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Models\ContentReport;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminReportsTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithAdminApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    public function test_global_admin_sees_the_moderation_queue_and_resolves_a_report_with_audit(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        [$post, $report] = $this->reportedPost();

        $this->signInAsAdmin($admin);

        $queue = $this->adminGet('/api/v1/admin/reports');
        $queue->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $report->public_id)
            ->assertJsonPath('data.0.status', 'open');

        $resolved = $this->adminPatch("/api/v1/admin/reports/{$report->public_id}/resolution", [
            'resolution' => 'actioned',
            'hide_content' => true,
            'note' => 'Removed for advertising.',
            'expected_version' => 1,
            'reason' => 'Confirmed spam after review.',
        ]);
        $resolved->assertOk()->assertJsonPath('data.status', 'actioned');

        $this->assertDatabaseHas('audit_events', [
            'action' => AuditAction::ReportResolved->value,
            'subject_type' => 'content_report',
            'subject_id' => $report->public_id,
            'actor_public_id' => $admin->public_id,
        ]);
        $this->assertSame('hidden_by_moderator', $post->fresh()->moderation_state->value);
    }

    public function test_a_scoped_moderator_only_sees_reports_in_their_communities(): void
    {
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create([
            'email' => 'mod@example.com',
            'password' => 'secret123',
        ]);
        [, $ownReport, $ownCommunity] = $this->reportedPost();
        CommunityMembership::factory()->moderator()->create([
            'community_id' => $ownCommunity->getKey(),
            'user_id' => $moderator->getKey(),
        ]);
        // A second community the moderator has no remit over.
        $this->reportedPost();

        $this->signInAsAdmin($moderator);

        $queue = $this->adminGet('/api/v1/admin/reports');
        $queue->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownReport->public_id);
    }

    public function test_resolution_requires_a_reason(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        [, $report] = $this->reportedPost();

        $this->signInAsAdmin($admin);

        $this->assertApiError(
            $this->adminPatch("/api/v1/admin/reports/{$report->public_id}/resolution", [
                'resolution' => 'dismissed',
                'expected_version' => 1,
            ]),
            422,
            ApiErrorCode::ValidationFailed,
        );
    }

    /** @return array{0: CommunityPost, 1: ContentReport, 2: Community} */
    private function reportedPost(): array
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

        return [$post, $report, $community];
    }
}
