<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Tools\Models\Tool;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminDemoAndAnalyticsTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithAdminApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    public function test_admin_seeds_demo_content_idempotently_with_audit(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($admin);
        $this->reauthenticateAdmin();

        $this->assertSame(0, Tool::query()->count());

        $first = $this->adminPost('/api/v1/admin/demo-data', ['reason' => 'Setting up a fresh environment.']);
        $first->assertOk();
        $this->assertGreaterThan(0, $first->json('data.catalog.tools'));
        $seeded = Tool::query()->count();

        // Idempotent: a second seed does not duplicate content.
        $this->adminPost('/api/v1/admin/demo-data', ['reason' => 'Re-run.'])->assertOk();
        $this->assertSame($seeded, Tool::query()->count());

        $this->assertDatabaseHas('audit_events', [
            'action' => AuditAction::DemoDataSeeded->value,
            'subject_type' => 'demo_data',
        ]);
    }

    public function test_admin_reads_a_privacy_safe_operational_overview(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        User::factory()->suspended()->create();

        $this->signInAsAdmin($admin);

        $response = $this->adminGet('/api/v1/admin/analytics');
        $response->assertOk()
            ->assertJsonPath('data.users.suspended', 1)
            ->assertJsonStructure([
                'data' => [
                    'users' => ['total', 'active', 'suspended', 'by_role'],
                    'content' => ['tools', 'prompts', 'workflows', 'templates'],
                    'community' => ['communities', 'memberships', 'reports'],
                    'mentors' => ['verified', 'unverified'],
                    'audit_event_count',
                ],
            ]);
        $this->assertGreaterThanOrEqual(2, $response->json('data.users.total'));
    }

    public function test_a_moderator_cannot_read_analytics_or_seed_demo_data(): void
    {
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create([
            'email' => 'mod@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($moderator);

        $this->assertApiError($this->adminGet('/api/v1/admin/analytics'), 403, ApiErrorCode::AuthorizationDenied);
        $this->assertApiError(
            $this->adminPost('/api/v1/admin/demo-data', ['reason' => 'No permission.']),
            403,
            ApiErrorCode::AuthorizationDenied,
        );
    }
}
