<?php

namespace Tests\Feature;

use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_the_public_api_status(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'status' => 'up',
                ],
            ]);
    }

    public function test_unknown_api_routes_do_not_leak_internal_details(): void
    {
        $response = $this->getJson('/api/v1/unknown');

        $response
            ->assertNotFound()
            ->assertJsonStructure(['message']);

        $payload = $response->json();

        $this->assertArrayNotHasKey('exception', $payload);
        $this->assertArrayNotHasKey('file', $payload);
        $this->assertArrayNotHasKey('line', $payload);
        $this->assertArrayNotHasKey('trace', $payload);
    }
}
