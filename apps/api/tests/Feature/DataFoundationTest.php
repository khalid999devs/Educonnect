<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class DataFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_keep_internal_bigint_keys_and_receive_public_ulids(): void
    {
        $user = User::factory()->create([
            'email' => '  STUDENT@Example.COM  ',
        ]);
        $publicId = (string) $user->public_id;

        $this->assertIsInt($user->getKey());
        $this->assertTrue(Str::isUlid($publicId));
        $this->assertMatchesRegularExpression('/^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$/', $publicId);
        $this->assertSame('student@example.com', $user->email);
        $this->assertSame('public_id', $user->getRouteKeyName());
        $this->assertArrayNotHasKey('id', $user->toArray());

        $replica = $user->replicate();
        $replica->email = 'replica@example.com';
        $replica->save();

        $this->assertNotSame($publicId, (string) $replica->public_id);
    }

    public function test_database_connection_and_datetime_columns_use_utc_timezone_semantics(): void
    {
        $connections = config('database.connections');
        $postgresConnection = config('database.connections.pgsql');

        $this->assertSame('pgsql', config('database.default'));
        $this->assertIsArray($connections);
        $this->assertSame(['pgsql'], array_keys($connections));
        $this->assertIsArray($postgresConnection);
        $this->assertArrayNotHasKey('url', $postgresConnection);
        $this->assertSame('UTC', config('database.connections.pgsql.timezone'));
        $this->assertSame('UTC', DB::scalar('SHOW TIME ZONE'));

        $expectedColumns = [
            'users' => ['email_verified_at', 'created_at', 'updated_at', 'last_login_at'],
            'password_reset_tokens' => ['created_at'],
            'personal_access_tokens' => ['last_used_at', 'expires_at', 'created_at', 'updated_at'],
            'failed_jobs' => ['failed_at'],
        ];

        foreach ($expectedColumns as $table => $columns) {
            foreach ($columns as $column) {
                $type = DB::table('information_schema.columns')
                    ->where('table_schema', 'public')
                    ->where('table_name', $table)
                    ->where('column_name', $column)
                    ->value('data_type');

                $this->assertSame('timestamp with time zone', $type, "{$table}.{$column} must use timestamptz.");

                $precision = DB::table('information_schema.columns')
                    ->where('table_schema', 'public')
                    ->where('table_name', $table)
                    ->where('column_name', $column)
                    ->value('datetime_precision');

                $this->assertSame(0, $precision, "{$table}.{$column} must retain Laravel's second precision.");
            }
        }

        $instant = CarbonImmutable::parse('2026-07-14T12:34:56+06:00');
        $user = User::factory()->create(['email_verified_at' => $instant]);
        $stored = $user->refresh()->email_verified_at;

        $this->assertInstanceOf(CarbonInterface::class, $stored);
        $this->assertSame($instant->getTimestamp(), $stored->getTimestamp());
    }

    public function test_postgresql_rejects_noncanonical_and_duplicate_email_values(): void
    {
        $first = User::factory()->create(['email' => 'student@example.com']);
        $second = User::factory()->create(['email' => 'other@example.com']);

        $this->assertDatabaseQueryRejected('23514', fn () => DB::table('users')
            ->where('id', $first->id)
            ->update(['email' => 'Student@Example.com']));

        $this->assertDatabaseQueryRejected('23514', fn () => DB::table('users')
            ->where('id', $first->id)
            ->update(['email' => "\tstudent@example.com"]));

        $this->assertDatabaseQueryRejected('23505', fn () => DB::table('users')
            ->where('id', $second->id)
            ->update(['email' => 'student@example.com']));

        $this->assertDatabaseQueryRejected('23514', fn () => DB::table('password_reset_tokens')->insert([
            'email' => 'Student@Example.com',
            'token' => 'test-token',
            'created_at' => now(),
        ]));
    }

    public function test_postgresql_enforces_public_id_integrity(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $rawUser = [
            'name' => 'Compatibility User',
            'email' => 'compatibility@example.com',
            'email_verified_at' => null,
            'password' => 'hashed-password-placeholder',
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'last_login_at' => null,
        ];

        $rawUserId = DB::table('users')->insertGetId($rawUser);
        $secondRawUserId = DB::table('users')->insertGetId([
            ...$rawUser,
            'email' => 'second-compatibility@example.com',
        ]);
        $databaseGeneratedPublicId = (string) DB::table('users')
            ->where('id', $rawUserId)
            ->value('public_id');
        $secondDatabaseGeneratedPublicId = (string) DB::table('users')
            ->where('id', $secondRawUserId)
            ->value('public_id');
        $databaseTimestampMilliseconds = (int) DB::scalar(
            'SELECT (EXTRACT(EPOCH FROM clock_timestamp()) * 1000)::bigint',
        );

        foreach ([$databaseGeneratedPublicId, $secondDatabaseGeneratedPublicId] as $publicId) {
            $this->assertTrue(Str::isUlid($publicId));
            $this->assertSame(strtolower($publicId), $publicId);
            $this->assertMatchesRegularExpression(
                '/^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$/',
                $publicId,
            );

            $generatedTimestampMilliseconds = (int) Ulid::fromString($publicId)
                ->getDateTime()
                ->format('Uv');

            $this->assertLessThanOrEqual(
                5_000,
                abs($databaseTimestampMilliseconds - $generatedTimestampMilliseconds),
                'Database-generated ULID timestamps must represent their creation time.',
            );
        }

        $this->assertNotSame($databaseGeneratedPublicId, $secondDatabaseGeneratedPublicId);

        $this->assertDatabaseQueryRejected('23514', fn () => DB::table('users')->insert([
            ...$rawUser,
            'email' => 'invalid-public-id-alphabet@example.com',
            'public_id' => '0'.str_repeat('i', 25),
        ]));

        $this->assertDatabaseQueryRejected('23514', fn () => DB::table('users')->insert([
            ...$rawUser,
            'email' => 'invalid-public-id-range@example.com',
            'public_id' => '8'.str_repeat('0', 25),
        ]));

        $this->assertDatabaseQueryRejected('23502', fn () => DB::table('users')->insert([
            ...$rawUser,
            'email' => 'null-public-id@example.com',
            'public_id' => null,
        ]));

        $this->assertDatabaseQueryRejected('23505', fn () => DB::table('users')->insert([
            ...$rawUser,
            'email' => 'duplicate-public-id@example.com',
            'public_id' => $first->public_id,
        ]));

        $this->assertDatabaseQueryRejected('23514', fn () => DB::table('users')
            ->where('id', $second->id)
            ->update(['public_id' => strtolower((string) Str::ulid())]));
    }

    public function test_sessions_allow_guests_reject_orphans_and_cascade_with_users(): void
    {
        $this->assertDatabaseQueryRejected('23503', fn () => DB::table('sessions')->insert([
            'id' => 'orphan-session',
            'user_id' => 999_999,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]));

        DB::table('sessions')->insert([
            'id' => 'guest-session',
            'user_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
        $this->assertDatabaseHas('sessions', ['id' => 'guest-session', 'user_id' => null]);

        $user = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'authenticated-session',
            'user_id' => $user->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $user->delete();

        $this->assertDatabaseMissing('sessions', ['id' => 'authenticated-session']);
    }

    public function test_default_seeder_is_repeatable_and_never_creates_an_account(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @param  Closure(): mixed  $operation
     */
    private function assertDatabaseQueryRejected(string $expectedSqlState, Closure $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            $this->assertSame($expectedSqlState, $exception->errorInfo[0] ?? null);

            return;
        }

        $this->fail("Expected PostgreSQL to reject the statement with SQLSTATE {$expectedSqlState}.");
    }
}
