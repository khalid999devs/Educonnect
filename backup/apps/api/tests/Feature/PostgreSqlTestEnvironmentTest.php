<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgreSqlTestEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_tests_use_the_dedicated_postgresql_database(): void
    {
        $connection = DB::connection();

        $this->assertSame('pgsql', $connection->getDriverName());
        $this->assertSame('educonnect_test', $connection->getDatabaseName());
        $this->assertStringStartsWith('18.', DB::scalar('show server_version'));
    }
}
