<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Users\Enums\AccountStatus;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminUsersTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithAdminApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    public function test_admin_lists_and_searches_users_with_account_fields_only(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'name' => 'Ada Admin',
        ]);
        User::factory()->create(['name' => 'Priya Patel', 'email' => 'priya@example.com']);
        User::factory()->suspended()->create(['name' => 'Sam Suspended', 'email' => 'sam@example.com']);

        $this->signInAsAdmin($admin);

        $all = $this->adminGet('/api/v1/admin/users');
        $all->assertOk();
        $this->assertGreaterThanOrEqual(3, count($all->json('data')));
        $this->assertArrayNotHasKey('password', $all->json('data.0'));

        $searched = $this->adminGet('/api/v1/admin/users?search=priya');
        $searched->assertOk()->assertJsonPath('data.0.email', 'priya@example.com');

        $suspendedOnly = $this->adminGet('/api/v1/admin/users?status=suspended');
        $suspendedOnly->assertOk()->assertJsonPath('data.0.email', 'sam@example.com');
        $this->assertCount(1, $suspendedOnly->json('data'));
    }

    public function test_admin_suspends_and_reactivates_a_user_with_an_audit_trail(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create(['email' => 'target@example.com']);

        $this->signInAsAdmin($admin);
        $this->reauthenticateAdmin();

        $suspended = $this->adminPost("/api/v1/admin/users/{$target->public_id}/suspension", [
            'reason' => 'Repeated policy violations in community posts.',
        ]);
        $suspended->assertOk()
            ->assertJsonPath('data.id', $target->public_id)
            ->assertJsonPath('data.status', AccountStatus::Suspended->value);
        $this->assertNotNull($suspended->json('data.suspended_at'));

        $this->assertDatabaseHas('audit_events', [
            'action' => AuditAction::UserSuspended->value,
            'actor_public_id' => $admin->public_id,
            'subject_type' => 'user',
            'subject_id' => $target->public_id,
        ]);
        $this->assertSame(AccountStatus::Suspended, $target->refresh()->status);

        $reactivated = $this->adminPost("/api/v1/admin/users/{$target->public_id}/reactivation", [
            'reason' => 'Appeal upheld after review.',
        ]);
        $reactivated->assertOk()->assertJsonPath('data.status', AccountStatus::Active->value);
        $this->assertNull($reactivated->json('data.suspended_at'));
        $this->assertDatabaseHas('audit_events', [
            'action' => AuditAction::UserReactivated->value,
            'subject_id' => $target->public_id,
        ]);
    }

    public function test_suspending_an_already_suspended_account_conflicts(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->suspended()->create();

        $this->signInAsAdmin($admin);
        $this->reauthenticateAdmin();

        $response = $this->adminPost("/api/v1/admin/users/{$target->public_id}/suspension", [
            'reason' => 'Trying to suspend twice.',
        ]);

        $this->assertApiError($response, 409, ApiErrorCode::Conflict);
    }

    public function test_an_admin_cannot_suspend_themselves(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $this->signInAsAdmin($admin);
        $this->reauthenticateAdmin();

        $response = $this->adminPost("/api/v1/admin/users/{$admin->public_id}/suspension", [
            'reason' => 'Should be refused.',
        ]);

        $this->assertApiError($response, 403, ApiErrorCode::AuthorizationDenied);
        $this->assertSame(AccountStatus::Active, $admin->refresh()->status);
    }

    public function test_an_admin_cannot_suspend_a_protected_administrator(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $peer = User::factory()->withRole(RoleKey::Admin)->create(['email' => 'peer@example.com']);

        $this->signInAsAdmin($admin);
        $this->reauthenticateAdmin();

        $response = $this->adminPost("/api/v1/admin/users/{$peer->public_id}/suspension", [
            'reason' => 'Should be refused: admin is protected.',
        ]);

        $this->assertApiError($response, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_a_super_admin_may_suspend_an_administrator(): void
    {
        $superAdmin = User::factory()->withRole(RoleKey::SuperAdmin)->create([
            'email' => 'super@example.com',
            'password' => 'secret123',
        ]);
        $admin = User::factory()->withRole(RoleKey::Admin)->create(['email' => 'admin@example.com']);

        $this->signInAsAdmin($superAdmin);
        $this->reauthenticateAdmin();

        $this->adminPost("/api/v1/admin/users/{$admin->public_id}/suspension", [
            'reason' => 'Super admin action within remit.',
        ])->assertOk();
        $this->assertSame(AccountStatus::Suspended, $admin->refresh()->status);
    }

    public function test_a_moderator_cannot_reach_user_management(): void
    {
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create([
            'email' => 'mod@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create();

        $this->signInAsAdmin($moderator);

        $this->assertApiError($this->adminGet('/api/v1/admin/users'), 403, ApiErrorCode::AuthorizationDenied);
        $this->assertApiError(
            $this->adminPost("/api/v1/admin/users/{$target->public_id}/suspension", ['reason' => 'No permission.']),
            403,
            ApiErrorCode::AuthorizationDenied,
        );
    }

    public function test_suspend_requires_a_reason_and_rejects_unknown_fields(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create();

        $this->signInAsAdmin($admin);
        $this->reauthenticateAdmin();

        $this->assertApiError(
            $this->adminPost("/api/v1/admin/users/{$target->public_id}/suspension", []),
            422,
            ApiErrorCode::ValidationFailed,
        );
        $this->assertApiError(
            $this->adminPost("/api/v1/admin/users/{$target->public_id}/suspension", [
                'reason' => 'Valid reason.',
                'unexpected' => 'x',
            ]),
            422,
            ApiErrorCode::ValidationFailed,
        );
    }

    public function test_a_suspended_user_cannot_sign_in(): void
    {
        User::factory()->suspended()->create([
            'email' => 'blocked@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ])->postJson('/api/v1/auth/login', [
            'email' => 'blocked@example.com',
            'password' => 'secret123',
        ]);

        $this->assertApiError($response, 422, ApiErrorCode::ValidationFailed);
        $this->assertGuest('web');
    }
}
