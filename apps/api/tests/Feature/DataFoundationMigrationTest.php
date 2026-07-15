<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class DataFoundationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_four_migrations_reconcile_existing_safe_brownfield_rows(): void
    {
        [$courses, $planner, $resources] = $this->lowerPhaseEightAcademicFoundation();
        $identity = $this->migration('2026_07_14_000000_add_public_id_and_email_integrity.php');
        $timestamps = $this->migration('2026_07_14_000001_standardize_database_timestamps_to_utc.php');
        $sessions = $this->migration('2026_07_14_000002_add_user_foreign_key_to_sessions.php');

        $sessions->down();
        $timestamps->down();
        $identity->down();

        $userId = $this->insertLegacyUser(" \tSTUDENT@Example.COM\r", '2026-07-14 06:34:56');
        DB::table('password_reset_tokens')->insert([
            'email' => "\tRESET@Example.COM ",
            'token' => 'legacy-token',
            'created_at' => '2026-07-14 06:34:56',
        ]);
        DB::table('sessions')->insert([
            'id' => 'legacy-session',
            'user_id' => $userId,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => 1_784_010_896,
        ]);

        config()->set('database.legacy_timestamp_timezone', 'UTC');

        $identity->up();
        $timestamps->up();
        $sessions->up();
        $courses->up();
        $planner->up();
        $resources->up();

        $user = User::query()->findOrFail($userId);
        $createdAt = $user->created_at;

        $this->assertSame('student@example.com', $user->email);
        $this->assertTrue(Str::isUlid((string) $user->public_id));
        $this->assertInstanceOf(CarbonInterface::class, $createdAt);
        $this->assertSame(1_784_010_896, $createdAt->getTimestamp());
        $this->assertSame(
            'reset@example.com',
            DB::table('password_reset_tokens')->value('email'),
        );
        $this->assertDatabaseHas('sessions', ['id' => 'legacy-session', 'user_id' => $userId]);
    }

    public function test_identity_migration_stops_on_normalized_email_duplicates(): void
    {
        $this->lowerPhaseEightAcademicFoundation();
        $identity = $this->migration('2026_07_14_000000_add_public_id_and_email_integrity.php');
        $identity->down();

        $this->insertLegacyUser('STUDENT@example.com');
        $this->insertLegacyUser("\tstudent@example.com ");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('normalized duplicates exist');

        $identity->up();
    }

    public function test_session_migration_stops_on_orphaned_authenticated_sessions(): void
    {
        $sessions = $this->migration('2026_07_14_000002_add_user_foreign_key_to_sessions.php');
        $sessions->down();

        DB::table('sessions')->insert([
            'id' => 'orphaned-legacy-session',
            'user_id' => 999_999,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => 1_784_010_896,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('orphaned authenticated sessions exist');

        $sessions->up();
    }

    public function test_timestamp_migration_requires_verified_utc_provenance_for_existing_values(): void
    {
        $timestamps = $this->migration('2026_07_14_000001_standardize_database_timestamps_to_utc.php');
        $timestamps->down();

        User::factory()->create();
        config()->set('database.legacy_timestamp_timezone', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_LEGACY_TIMESTAMP_TIMEZONE=UTC');

        $timestamps->up();
    }

    public function test_full_phase_four_migration_stops_before_any_write_when_timestamp_provenance_is_unknown(): void
    {
        $this->lowerPhaseEightAcademicFoundation();
        $identity = $this->migration('2026_07_14_000000_add_public_id_and_email_integrity.php');
        $timestamps = $this->migration('2026_07_14_000001_standardize_database_timestamps_to_utc.php');
        $sessions = $this->migration('2026_07_14_000002_add_user_foreign_key_to_sessions.php');

        $sessions->down();
        $timestamps->down();
        $identity->down();

        DB::table('migrations')->whereIn('migration', [
            '2026_07_14_000000_add_public_id_and_email_integrity',
            '2026_07_14_000001_standardize_database_timestamps_to_utc',
            '2026_07_14_000002_add_user_foreign_key_to_sessions',
        ])->delete();

        $userId = $this->insertLegacyUser('MixedCase@Example.COM', '2026-07-14 06:34:56');
        config()->set('database.legacy_timestamp_timezone', null);

        $exitCode = 1;
        $failureMessage = '';

        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $failureMessage = Artisan::output();
        } catch (Throwable $exception) {
            $failureMessage = $exception->getMessage();
        }

        $this->assertNotSame(0, $exitCode);
        $this->assertStringContainsString('DB_LEGACY_TIMESTAMP_TIMEZONE=UTC', $failureMessage);
        $this->assertFalse(Schema::hasColumn('users', 'public_id'));
        $this->assertSame('MixedCase@Example.COM', DB::table('users')->where('id', $userId)->value('email'));
        $this->assertNull(DB::scalar("SELECT to_regprocedure('educonnect_generate_ulid()')"));
    }

    public function test_phase_four_entrypoint_stops_on_orphaned_sessions_before_any_write(): void
    {
        $this->lowerPhaseEightAcademicFoundation();
        $identity = $this->migration('2026_07_14_000000_add_public_id_and_email_integrity.php');
        $sessions = $this->migration('2026_07_14_000002_add_user_foreign_key_to_sessions.php');

        $sessions->down();
        $identity->down();

        DB::table('sessions')->insert([
            'id' => 'orphaned-preflight-session',
            'user_id' => 999_999,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => 1_784_010_896,
        ]);

        try {
            $identity->up();
            self::fail('The Phase 4 entrypoint accepted an orphaned authenticated session.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('orphaned authenticated sessions exist', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('users', 'public_id'));
        $this->assertNull(DB::scalar("SELECT to_regprocedure('educonnect_generate_ulid()')"));
    }

    private function migration(string $file): Migration
    {
        $migration = require database_path('migrations/'.$file);

        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    /** @return array{Migration, Migration, Migration} */
    private function lowerPhaseEightAcademicFoundation(): array
    {
        $resources = $this->migration('2026_07_14_000009_create_resource_storage_foundation.php');
        $planner = $this->migration('2026_07_14_000008_create_planner_foundation.php');
        $courses = $this->migration('2026_07_14_000007_create_courses_and_academic_terms.php');
        $resources->down();
        $planner->down();
        $courses->down();

        return [$courses, $planner, $resources];
    }

    private function insertLegacyUser(string $email, ?string $timestamp = null): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Legacy Student',
            'email' => $email,
            'email_verified_at' => $timestamp,
            'password' => 'legacy-password-hash',
            'remember_token' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'last_login_at' => null,
        ]);
    }
}
