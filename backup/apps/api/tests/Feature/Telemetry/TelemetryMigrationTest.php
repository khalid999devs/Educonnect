<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TelemetryMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_telemetry_table_exists_with_operational_columns(): void
    {
        self::assertTrue(Schema::hasTable('telemetry_events'));
        self::assertTrue(Schema::hasColumns('telemetry_events', [
            'id', 'type', 'name', 'outcome', 'status_code', 'duration_ms', 'metadata', 'occurred_at',
        ]));
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('telemetry_events')->insert([
            'type' => 'not_a_type',
            'name' => 'intake.classification',
            'outcome' => 'success',
            'metadata' => '{}',
            'occurred_at' => now(),
        ]);
    }

    public function test_a_malformed_name_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('telemetry_events')->insert([
            'type' => 'ai_call',
            'name' => 'Intake Classification', // spaces and capitals are not allowed
            'outcome' => 'success',
            'metadata' => '{}',
            'occurred_at' => now(),
        ]);
    }

    public function test_metadata_must_be_a_json_object(): void
    {
        $this->expectException(QueryException::class);

        DB::table('telemetry_events')->insert([
            'type' => 'ai_call',
            'name' => 'intake.classification',
            'outcome' => 'success',
            'metadata' => '["not", "an", "object"]',
            'occurred_at' => now(),
        ]);
    }
}
