<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Users\Enums\AccountStatus;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminReauthenticationTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithAdminApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    public function test_a_high_risk_action_is_locked_without_a_recent_reauthentication(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create();

        $this->signInAsAdmin($admin);

        // Capability is satisfied, but no step-up grant exists yet.
        $response = $this->adminPost("/api/v1/admin/users/{$target->public_id}/suspension", [
            'reason' => 'Attempting without a fresh confirmation.',
        ]);

        $this->assertApiError($response, 423, ApiErrorCode::ReauthenticationRequired);
        $this->assertSame(AccountStatus::Active, $target->refresh()->status);
    }

    public function test_reauthentication_with_the_wrong_password_is_rejected_and_stays_locked(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create();

        $this->signInAsAdmin($admin);

        $reauth = $this->adminPost('/api/v1/admin/auth/reauth', ['password' => 'wrong-password']);
        $this->assertApiError($reauth, 422, ApiErrorCode::ValidationFailed);

        $suspension = $this->adminPost("/api/v1/admin/users/{$target->public_id}/suspension", [
            'reason' => 'Still locked after a failed confirmation.',
        ]);
        $this->assertApiError($suspension, 423, ApiErrorCode::ReauthenticationRequired);
    }

    public function test_reauthentication_unlocks_high_risk_actions_within_the_window(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create();

        $this->signInAsAdmin($admin);

        $reauth = $this->adminPost('/api/v1/admin/auth/reauth', ['password' => 'secret123']);
        $reauth->assertOk();
        $this->assertNotNull($reauth->json('data.reauthenticated_until'));

        $this->adminPost("/api/v1/admin/users/{$target->public_id}/suspension", [
            'reason' => 'Confirmed, so this proceeds.',
        ])->assertOk();

        $this->assertSame(AccountStatus::Suspended, $target->refresh()->status);
    }

    public function test_the_reauthentication_grant_expires_after_the_configured_window(): void
    {
        config()->set('auth.admin_reauth_timeout', 300);

        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $target = User::factory()->create();

        $this->signInAsAdmin($admin);
        $this->reauthenticateAdmin();

        // Past the window, the same session must confirm again.
        $this->travel(301)->seconds(function () use ($target): void {
            $response = $this->adminPost("/api/v1/admin/users/{$target->public_id}/suspension", [
                'reason' => 'The grant has expired.',
            ]);

            $this->assertApiError($response, 423, ApiErrorCode::ReauthenticationRequired);
        });

        $this->assertSame(AccountStatus::Active, $target->refresh()->status);
    }

    public function test_reading_admin_routes_does_not_require_reauthentication(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $this->signInAsAdmin($admin);

        // A capability-gated read is unaffected by the step-up requirement.
        $this->adminGet('/api/v1/admin/users')->assertOk();
    }
}
