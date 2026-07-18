<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class AccountStatusMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_default_to_active_without_a_suspension_timestamp(): void
    {
        $user = User::factory()->create();

        $this->assertSame('active', DB::table('users')->where('id', $user->getKey())->value('status'));
        $this->assertNull(DB::table('users')->where('id', $user->getKey())->value('suspended_at'));
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->assertQueryRejected('23514', static fn () => DB::table('users')
            ->where('id', $user->getKey())
            ->update(['status' => 'archived']));
    }

    public function test_status_and_suspension_timestamp_must_be_consistent(): void
    {
        $user = User::factory()->create();

        // Suspended without a timestamp.
        $this->assertQueryRejected('23514', static fn () => DB::table('users')
            ->where('id', $user->getKey())
            ->update(['status' => 'suspended', 'suspended_at' => null]));

        // Active with a lingering timestamp.
        $this->assertQueryRejected('23514', static fn () => DB::table('users')
            ->where('id', $user->getKey())
            ->update(['status' => 'active', 'suspended_at' => now()]));
    }

    public function test_rollback_refuses_while_suspended_accounts_exist(): void
    {
        User::factory()->suspended()->create();

        $this->expectException(RuntimeException::class);

        Artisan::call('migrate:rollback', ['--step' => 1]);
    }

    private function assertQueryRejected(string $expectedSqlState, callable $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            self::assertSame($expectedSqlState, $exception->errorInfo[0] ?? null);

            return;
        }

        self::fail("Expected PostgreSQL to reject the statement with SQLSTATE {$expectedSqlState}.");
    }
}
