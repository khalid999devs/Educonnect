<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Community\Models\Community;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminCommunityManagementTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithAdminApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    public function test_admin_creates_edits_and_archives_a_community_with_audit(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($admin);

        $created = $this->adminPost('/api/v1/admin/communities', [
            'name' => 'Thesis Writers',
            'summary' => 'Support for students writing a thesis.',
            'description' => 'Share drafts, deadlines, and feedback.',
            'topic' => 'Academic writing',
        ]);
        $created->assertCreated()
            ->assertJsonPath('data.name', 'Thesis Writers')
            ->assertJsonPath('data.visibility', 'published')
            ->assertJsonPath('data.slug', 'thesis-writers');
        $communityId = $created->json('data.id');

        $this->adminPut("/api/v1/admin/communities/{$communityId}", [
            'name' => 'Thesis Writers Circle',
            'summary' => 'Support for students writing a thesis.',
            'description' => 'Share drafts, deadlines, and feedback.',
            'topic' => 'Academic writing',
            'expected_version' => 1,
        ])->assertOk()->assertJsonPath('data.name', 'Thesis Writers Circle');

        $archived = $this->adminPatch("/api/v1/admin/communities/{$communityId}/visibility", [
            'visibility' => 'archived',
            'expected_version' => 2,
            'reason' => 'Low activity; retiring the group.',
        ]);
        $archived->assertOk()->assertJsonPath('data.visibility', 'archived');

        $this->assertDatabaseHas('audit_events', [
            'action' => AuditAction::CommunityVisibilityChanged->value,
            'subject_type' => 'community',
            'subject_id' => $communityId,
        ]);
    }

    public function test_admin_lists_all_communities_including_archived(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        Community::factory()->create(['visibility' => 'published']);
        Community::factory()->create(['visibility' => 'archived']);

        $this->signInAsAdmin($admin);

        $this->adminGet('/api/v1/admin/communities')->assertOk()->assertJsonCount(2, 'data');
        $this->adminGet('/api/v1/admin/communities?visibility=archived')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_moderator_cannot_manage_communities(): void
    {
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create([
            'email' => 'mod@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($moderator);

        $this->assertApiError($this->adminGet('/api/v1/admin/communities'), 403, ApiErrorCode::AuthorizationDenied);
    }
}
