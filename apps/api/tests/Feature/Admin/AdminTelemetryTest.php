<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminTelemetryTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithAdminApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    public function test_admin_reads_operational_telemetry_aggregates(): void
    {
        $this->seedTelemetry();

        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($admin);

        $response = $this->adminGet('/api/v1/admin/telemetry');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'window_hours',
                    'generated_at',
                    'ai' => ['total', 'by_outcome', 'fallback_rate', 'failure_rate', 'latency_ms', 'by_feature'],
                    'jobs' => ['total', 'by_outcome', 'failure_rate', 'by_job'],
                    'errors' => ['total', 'by_code'],
                    'http' => ['request_count', 'error_count', 'error_rate', 'latency_ms', 'window_seconds'],
                ],
            ]);

        // Two AI successes and one fallback were seeded within the window.
        $this->assertSame(3, $response->json('data.ai.total'));
        $this->assertSame(1, $response->json('data.ai.by_outcome.fallback'));
        $this->assertEqualsWithDelta(0.3333, $response->json('data.ai.fallback_rate'), 0.001);
        $this->assertNotNull($response->json('data.ai.latency_ms.p95'));

        // The captured server error surfaces by code, and a stale event is excluded.
        $this->assertSame(1, $response->json('data.errors.total'));
        $this->assertSame(1, $response->json('data.errors.by_code.internal_error'));
    }

    public function test_a_moderator_cannot_read_telemetry(): void
    {
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create([
            'email' => 'mod@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($moderator);

        $this->assertApiError($this->adminGet('/api/v1/admin/telemetry'), 403, ApiErrorCode::AuthorizationDenied);
    }

    private function seedTelemetry(): void
    {
        $now = now();
        $rows = [
            ['type' => 'ai_call', 'name' => 'intake.classification', 'outcome' => 'success', 'duration_ms' => 300],
            ['type' => 'ai_call', 'name' => 'copilot', 'outcome' => 'success', 'duration_ms' => 1200],
            ['type' => 'ai_call', 'name' => 'intake.classification', 'outcome' => 'fallback', 'duration_ms' => 5],
            ['type' => 'error', 'name' => 'internal_error', 'outcome' => 'failure', 'status_code' => 500, 'duration_ms' => null],
        ];

        foreach ($rows as $row) {
            DB::table('telemetry_events')->insert(array_merge([
                'status_code' => null,
                'duration_ms' => null,
                'metadata' => '{}',
                'occurred_at' => $now,
            ], $row));
        }

        // A stale error outside the 24h window must be excluded from the overview.
        DB::table('telemetry_events')->insert([
            'type' => 'error',
            'name' => 'internal_error',
            'outcome' => 'failure',
            'status_code' => 500,
            'duration_ms' => null,
            'metadata' => '{}',
            'occurred_at' => now()->subDays(2),
        ]);
    }
}
