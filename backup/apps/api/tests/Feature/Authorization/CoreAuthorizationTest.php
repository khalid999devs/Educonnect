<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Auth\Actions\RegisterUserAction;
use App\Domains\Authorization\Actions\ChangeUserRolesAction;
use App\Domains\Authorization\Actions\SyncRoleCapabilitiesAction;
use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Authorization\Models\Role;
use App\Domains\Users\Models\User;
use App\Support\RequestId;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class CoreAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_backfills_existing_users_and_default_seeding_is_idempotent_without_accounts(): void
    {
        $auditMigration = $this->migration('2026_07_14_000004_create_append_only_audit_events.php');
        $authorizationMigration = $this->migration('2026_07_14_000003_create_authorization_foundation.php');
        $auditMigration->down();
        $authorizationMigration->down();

        $legacyUserId = DB::table('users')->insertGetId([
            'name' => 'Legacy Student',
            'email' => 'legacy-authorization@example.com',
            'email_verified_at' => now(),
            'password' => 'legacy-password-hash',
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'last_login_at' => null,
        ]);

        $authorizationMigration->up();
        $auditMigration->up();

        $this->assertDatabaseHas('role_user', [
            'user_id' => $legacyUserId,
            'role_id' => Role::query()->where('key', RoleKey::Student->value)->value('id'),
        ]);
        $this->assertDatabaseCount('roles', count(RoleKey::cases()));
        $this->assertDatabaseCount('capabilities', count(CapabilityKey::cases()));

        DB::table('users')->where('id', $legacyUserId)->delete();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', count(RoleKey::cases()));
        $this->assertDatabaseCount('capabilities', count(CapabilityKey::cases()));
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_registration_transactionally_assigns_student_and_role_fields_are_not_mass_assignable(): void
    {
        Notification::fake();

        $user = $this->app->make(RegisterUserAction::class)->execute([
            'name' => 'New Student',
            'email' => 'new-student@example.com',
            'password' => 'secret123',
        ]);

        $this->assertTrue($user->hasRole(RoleKey::Student));
        $this->assertSame(RoleKey::Student, $user->primaryRoleKey());

        $user->fill([
            'role_id' => 999,
            'role_keys' => [RoleKey::SuperAdmin->value],
        ]);

        $this->assertArrayNotHasKey('role_id', $user->getAttributes());
        $this->assertArrayNotHasKey('role_keys', $user->getAttributes());
        $this->assertFalse($user->hasRole(RoleKey::SuperAdmin));
    }

    public function test_only_super_admin_can_change_protected_roles_and_the_final_super_admin_is_retained(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create();
        $target = User::factory()->create();
        $changeRoles = $this->app->make(ChangeUserRolesAction::class);

        $changeRoles->execute(
            target: $target,
            roles: [RoleKey::Moderator],
            actor: $admin,
            reason: 'Assign moderation duties.',
            requestId: $this->requestId(),
        );
        $this->assertTrue($target->fresh()->hasRole(RoleKey::Moderator));

        try {
            $changeRoles->execute(
                target: $target,
                roles: [RoleKey::Admin],
                actor: $admin,
                reason: 'Attempt protected assignment.',
                requestId: $this->requestId(),
            );
            self::fail('An admin changed a protected role.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $superAdmin = User::factory()->withRole(RoleKey::SuperAdmin)->create();
        $changeRoles->execute(
            target: $target,
            roles: [RoleKey::SuperAdmin],
            actor: $superAdmin,
            reason: 'Create a second emergency administrator.',
            requestId: $this->requestId(),
        );
        $changeRoles->execute(
            target: $superAdmin,
            roles: [RoleKey::Student],
            actor: $superAdmin,
            reason: 'Transfer emergency administration.',
            requestId: $this->requestId(),
        );

        $lastSuperAdmin = $target->fresh();
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('final super administrator');

        $changeRoles->execute(
            target: $lastSuperAdmin,
            roles: [RoleKey::Student],
            actor: $lastSuperAdmin,
            reason: 'Invalid removal of final administrator.',
            requestId: $this->requestId(),
        );
    }

    public function test_exact_role_noop_creates_no_audit_and_revokes_no_session_or_token(): void
    {
        $actor = User::factory()->withRole(RoleKey::SuperAdmin)->create();
        $target = User::factory()->withRole(RoleKey::Moderator)->create();
        $this->createSessionAndToken($target, 'noop');

        $this->app->make(ChangeUserRolesAction::class)->execute(
            target: $target,
            roles: [RoleKey::Moderator],
            actor: $actor,
            reason: 'No effective role change.',
            requestId: $this->requestId(),
        );

        $this->assertDatabaseCount('audit_events', 0);
        $this->assertDatabaseHas('sessions', ['id' => 'noop-session']);
        $this->assertDatabaseHas('personal_access_tokens', ['token' => hash('sha256', 'noop-token')]);
    }

    public function test_capability_changes_are_audited_and_revoke_sessions_atomically(): void
    {
        $actor = User::factory()->withRole(RoleKey::SuperAdmin)->create();
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create();
        $role = Role::query()->where('key', RoleKey::Moderator->value)->firstOrFail();
        $sync = $this->app->make(SyncRoleCapabilitiesAction::class);
        $this->createSessionAndToken($moderator, 'capability');

        $sync->execute(
            role: $role,
            capabilities: [CapabilityKey::AdminAccess],
            actor: $actor,
            reason: 'Reduce moderator access during review.',
            requestId: $this->requestId(),
        );

        $event = AuditEvent::query()->sole();
        $this->assertSame('authorization.role-capabilities-changed', $event->action);
        $this->assertSame($actor->public_id, $event->actor_public_id);
        $this->assertSame(['capability_keys'], array_keys($event->before_state));
        $this->assertSame(['capability_keys'], array_keys($event->after_state));
        $this->assertMatchesRegularExpression(RequestId::PATTERN, $event->request_id);
        $this->assertDatabaseMissing('sessions', ['id' => 'capability-session']);
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => hash('sha256', 'capability-token')]);

        $this->createSessionAndToken($moderator, 'rollback');

        try {
            $sync->execute(
                role: $role,
                capabilities: [CapabilityKey::AdminAccess, CapabilityKey::ModerationScoped],
                actor: $actor,
                reason: '',
                requestId: $this->requestId(),
            );
            self::fail('A capability mutation without an audit reason succeeded.');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(
            [CapabilityKey::AdminAccess->value],
            $role->fresh()->capabilities()->pluck('capabilities.key')->all(),
        );
        $this->assertDatabaseHas('sessions', ['id' => 'rollback-session']);
        $this->assertDatabaseHas('personal_access_tokens', ['token' => hash('sha256', 'rollback-token')]);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_bootstrap_is_reason_required_one_use_and_audit_rows_are_guarded_and_immutable(): void
    {
        $target = User::factory()->create();

        $this->assertSame(1, Artisan::call('authorization:bootstrap-super-admin', [
            'user' => $target->public_id,
            '--confirm' => true,
        ]));
        $this->assertSame(0, Artisan::call('authorization:bootstrap-super-admin', [
            'user' => $target->public_id,
            '--reason' => 'Establish the initial emergency administrator.',
            '--confirm' => true,
        ]));
        $this->assertTrue($target->fresh()->hasRole(RoleKey::SuperAdmin));

        $secondTarget = User::factory()->create();
        $this->assertSame(1, Artisan::call('authorization:bootstrap-super-admin', [
            'user' => $secondTarget->public_id,
            '--reason' => 'Attempt a second bootstrap.',
            '--confirm' => true,
        ]));

        $event = AuditEvent::query()->sole();
        $originalReason = $event->reason;

        try {
            $event->fill(['reason' => 'tampered']);
            self::fail('Audit metadata was mass assignable.');
        } catch (MassAssignmentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($originalReason, $event->reason);

        $this->assertAuditMutationRejected(fn () => DB::table('audit_events')
            ->where('id', $event->getKey())
            ->update(['reason' => 'tampered']));
        $this->assertAuditMutationRejected(fn () => DB::table('audit_events')
            ->where('id', $event->getKey())
            ->delete());
        $this->assertDatabaseHas('audit_events', [
            'id' => $event->getKey(),
            'reason' => $originalReason,
        ]);
    }

    private function migration(string $file): Migration
    {
        $migration = require database_path('migrations/'.$file);
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function requestId(): string
    {
        return 'req_'.bin2hex(random_bytes(16));
    }

    private function createSessionAndToken(User $user, string $prefix): void
    {
        DB::table('sessions')->insert([
            'id' => $prefix.'-session',
            'user_id' => $user->getKey(),
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->getKey(),
            'name' => $prefix,
            'token' => hash('sha256', $prefix.'-token'),
            'abilities' => null,
            'last_used_at' => null,
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  callable(): mixed  $mutation
     */
    private function assertAuditMutationRejected(callable $mutation): void
    {
        try {
            DB::transaction($mutation);
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0] ?? null);

            return;
        }

        self::fail('PostgreSQL accepted a mutation of append-only audit evidence.');
    }
}
