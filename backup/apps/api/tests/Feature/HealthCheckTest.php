<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ApiErrorCode;
use App\Support\RequestId;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use SessionHandlerInterface;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    use AssertsApiResponses;

    public function test_legacy_health_endpoint_remains_a_compatible_liveness_alias(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'up')
            ->assertJsonCount(2);

        $this->assertSuccessRequestId($response);
    }

    public function test_versioned_health_endpoint_returns_the_standard_envelope(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'up')
            ->assertJsonCount(2);

        $this->assertSuccessRequestId($response);
    }

    public function test_request_ids_are_server_generated_unique_and_browser_visible(): void
    {
        $first = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            RequestId::HEADER => 'client-controlled-value',
        ])->getJson('/api/v1/health');
        $second = $this->getJson('/api/v1/health');

        $firstRequestId = $this->assertSuccessRequestId($first);
        $secondRequestId = $this->assertSuccessRequestId($second);

        $this->assertNotSame('client-controlled-value', $firstRequestId);
        $this->assertNotSame($firstRequestId, $secondRequestId);
        $first->assertHeader('Access-Control-Expose-Headers', RequestId::HEADER);
    }

    public function test_health_probes_never_start_a_first_party_spa_session(): void
    {
        $unavailableSessionBackend = new class implements SessionHandlerInterface
        {
            public function open(string $path, string $name): bool
            {
                return true;
            }

            public function close(): bool
            {
                return true;
            }

            public function read(string $id): string|false
            {
                throw new RuntimeException('The unavailable session backend must not be read by a health probe.');
            }

            public function write(string $id, string $data): bool
            {
                throw new RuntimeException('The unavailable session backend must not be written by a health probe.');
            }

            public function destroy(string $id): bool
            {
                return true;
            }

            public function gc(int $max_lifetime): int|false
            {
                return 0;
            }
        };

        Session::extend(
            'intentionally-unavailable',
            static fn (): SessionHandlerInterface => $unavailableSessionBackend,
        );
        Config::set('session.driver', 'intentionally-unavailable');

        foreach (['/api/health', '/api/v1/health', '/api/v1/health/readiness'] as $path) {
            $response = $this->withHeaders([
                'Origin' => 'http://localhost:3000',
                'Referer' => 'http://localhost:3000/health-monitor',
            ])->getJson($path);

            $response->assertOk()->assertHeaderMissing('Set-Cookie');
            $this->assertSuccessRequestId($response);
        }
    }

    public function test_liveness_stays_up_while_readiness_goes_down_during_maintenance(): void
    {
        $this->app->maintenanceMode()->activate(['status' => 503]);

        try {
            $liveness = $this->getJson('/api/v1/health');
            $readiness = $this->getJson('/api/v1/health/readiness');
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }

        $liveness->assertOk()->assertJsonPath('data.status', 'up');
        $this->assertSuccessRequestId($liveness);
        $this->assertApiError($readiness, 503, ApiErrorCode::ServiceUnavailable);
    }

    public function test_unknown_api_routes_use_the_redacted_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/unknown');

        $this->assertApiError($response, 404, ApiErrorCode::ResourceNotFound);
        $response->assertJsonPath('error.message', 'The requested resource was not found.');
    }

    public function test_method_not_allowed_preserves_semantic_headers(): void
    {
        $response = $this->postJson('/api/v1/health');

        $this->assertApiError($response, 405, ApiErrorCode::MethodNotAllowed);
        $this->assertStringContainsString('GET', (string) $response->headers->get('Allow'));
    }

    public function test_unexpected_api_errors_never_expose_internal_details(): void
    {
        Route::get('/api/v1/testing/internal-error', static function (): never {
            throw new RuntimeException('SQLSTATE secret-host internal failure');
        });

        $response = $this->getJson('/api/v1/testing/internal-error');

        $this->assertApiError($response, 500, ApiErrorCode::InternalError);
        $response->assertJsonPath('error.message', 'An unexpected error occurred.');

        $content = $response->getContent();

        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('secret-host', $content);
        $this->assertStringNotContainsString(RuntimeException::class, $content);
    }
}
