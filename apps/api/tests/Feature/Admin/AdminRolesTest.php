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

final class AdminRolesTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithAdminApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    public function test_admin_lists_roles_with_their_capabilities(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $this->signInAsAdmin($admin);

        $response = $this->adminGet('/api/v1/admin/roles');
        $response->assertOk();
        $keys = array_column($response->json('data'), 'key');
        $this->assertContains(RoleKey::Admin->value, $keys);
        $this->assertContains(RoleKey::SuperAdmin->value, $keys);
        $adminRow = collect($response->json('data'))->firstWhere('key', RoleKey::Admin->value);
        $this->assertTrue($adminRow['is_protected']);
        $this->assertContains('users.suspend', $adminRow['capabilities']);
    }

    public function test_admin_assigns_a_non_protected_role_with_an_audit_trail(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create(['email' => 'target@example.com']);

        $this->signInAsAdmin($admin);

        $response = $this->adminPut("/api/v1/admin/users/{$target->public_id}/roles", [
            'roles' => [RoleKey::Student->value, RoleKey::Mentor->value],
            'reason' => 'Approved as a peer mentor.',
        ]);

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [RoleKey::Mentor->value, RoleKey::Student->value],
            $response->json('data.roles'),
        );
        $this->assertTrue($target->fresh()->hasRole(RoleKey::Mentor));
        $this->assertDatabaseHas('audit_events', [
            'action' => AuditAction::UserRolesChanged->value,
            'subject_id' => $target->public_id,
            'actor_public_id' => $admin->public_id,
        ]);
    }

    public function test_an_admin_cannot_grant_a_protected_role(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create();

        $this->signInAsAdmin($admin);

        $response = $this->adminPut("/api/v1/admin/users/{$target->public_id}/roles", [
            'roles' => [RoleKey::Admin->value],
            'reason' => 'Attempting to escalate.',
        ]);

        $this->assertApiError($response, 403, ApiErrorCode::AuthorizationDenied);
        $this->assertFalse($target->fresh()->hasRole(RoleKey::Admin));
    }

    public function test_a_super_admin_may_grant_a_protected_role(): void
    {
        $superAdmin = User::factory()->withRole(RoleKey::SuperAdmin)->create([
            'email' => 'super@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create();

        $this->signInAsAdmin($superAdmin);

        $this->adminPut("/api/v1/admin/users/{$target->public_id}/roles", [
            'roles' => [RoleKey::Admin->value],
            'reason' => 'Onboarding a new administrator.',
        ])->assertOk();
        $this->assertTrue($target->fresh()->hasRole(RoleKey::Admin));
    }

    public function test_a_moderator_cannot_view_or_assign_roles(): void
    {
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create([
            'email' => 'mod@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create();

        $this->signInAsAdmin($moderator);

        $this->assertApiError($this->adminGet('/api/v1/admin/roles'), 403, ApiErrorCode::AuthorizationDenied);
        $this->assertApiError(
            $this->adminPut("/api/v1/admin/users/{$target->public_id}/roles", [
                'roles' => [RoleKey::Mentor->value],
                'reason' => 'No permission.',
            ]),
            403,
            ApiErrorCode::AuthorizationDenied,
        );
    }
}
