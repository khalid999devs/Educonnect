<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminAuditLogTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithAdminApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    public function test_admin_reads_the_audit_trail_and_filters_by_action(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create();

        $this->signInAsAdmin($admin);

        // Generate a real audit event by performing a sensitive action.
        $this->adminPost("/api/v1/admin/users/{$target->public_id}/suspension", [
            'reason' => 'Generating an audit event for the log.',
        ])->assertOk();

        $log = $this->adminGet('/api/v1/admin/audit-events');
        $log->assertOk()
            ->assertJsonPath('data.0.action', AuditAction::UserSuspended->value)
            ->assertJsonPath('data.0.actor.id', $admin->public_id)
            ->assertJsonPath('data.0.subject.id', $target->public_id);
        $this->assertSame(
            ['account_status' => ['suspended']],
            $log->json('data.0.after_state'),
        );

        $filtered = $this->adminGet('/api/v1/admin/audit-events?action='.AuditAction::UserReactivated->value);
        $filtered->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_moderator_without_view_all_cannot_read_the_audit_log(): void
    {
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create([
            'email' => 'mod@example.com',
            'password' => 'secret123',
        ]);

        $this->signInAsAdmin($moderator);

        $this->assertApiError(
            $this->adminGet('/api/v1/admin/audit-events'),
            403,
            ApiErrorCode::AuthorizationDenied,
        );
    }

    public function test_the_audit_log_requires_admin_access(): void
    {
        // A verified student has no admin access and cannot even open an admin session.
        $student = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'secret123',
        ]);

        $denied = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/auth/login', [
                'email' => $student->email,
                'password' => 'secret123',
            ]);

        $this->assertApiError($denied, 422, ApiErrorCode::ValidationFailed);
    }
}
