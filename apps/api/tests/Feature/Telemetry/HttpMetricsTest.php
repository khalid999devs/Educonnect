<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Domains\Telemetry\Support\HttpMetricsStore;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HttpMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowserBoundary();
        config()->set('telemetry.http.enabled', true);
    }

    public function test_api_requests_are_recorded_in_the_rolling_histogram(): void
    {
        ToolCategory::factory()->create(['slug' => 'research-support']);
        $user = User::factory()->create();

        $this->actingAs($user, 'web');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/guidance?category=research-support')
            ->assertOk();

        $snapshot = app(HttpMetricsStore::class)->snapshot();
        self::assertGreaterThanOrEqual(1, $snapshot['request_count']);
        self::assertSame(0, $snapshot['error_count']);
        self::assertNotNull($snapshot['latency_ms']['p50']);
    }

    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'http-metrics-test-token',
        ];
    }

    private function configureBrowserBoundary(): void
    {
        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }
}
