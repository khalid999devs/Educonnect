<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Domains\Telemetry\Models\TelemetryEvent;
use App\Support\ApiExceptionRenderer;
use App\Support\RequestId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ErrorCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('telemetry.enabled', true);
    }

    public function test_a_server_fault_is_captured_as_redacted_telemetry(): void
    {
        $request = Request::create('/api/v1/intake/links', 'POST');
        $request->attributes->set(RequestId::ATTRIBUTE, 'req_'.str_repeat('a', 32));
        $response = new Response('', 500);

        $rendered = app(ApiExceptionRenderer::class)->render(
            $response,
            new RuntimeException('secret database details that must never leak'),
            $request,
        );

        // The response stays redacted: no exception message, a stable code.
        self::assertSame(500, $rendered->getStatusCode());
        self::assertStringNotContainsString('secret database details', (string) $rendered->getContent());

        $event = TelemetryEvent::query()->where('type', 'error')->firstOrFail();
        self::assertSame('internal_error', $event->getAttribute('name'));
        self::assertSame(500, $event->getAttribute('status_code'));
        self::assertSame(RuntimeException::class, $event->getAttribute('metadata')['exception']);
        self::assertSame('req_'.str_repeat('a', 32), $event->getAttribute('metadata')['request_id']);
    }

    public function test_client_errors_are_not_captured_as_server_faults(): void
    {
        $request = Request::create('/api/v1/intake/links', 'POST');
        $response = new Response('', 422);

        app(ApiExceptionRenderer::class)->render($response, new RuntimeException('validation'), $request);

        self::assertSame(0, TelemetryEvent::query()->where('type', 'error')->count());
    }
}
