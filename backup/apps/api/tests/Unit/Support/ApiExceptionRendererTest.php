<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ApiExceptionRenderer;
use App\Support\RequestId;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ApiExceptionRendererTest extends TestCase
{
    private const REQUEST_ID = 'req_0123456789abcdef0123456789abcdef';

    public function test_non_error_exception_responses_are_normalized_to_an_internal_error(): void
    {
        $request = $this->bindRequestId();
        $response = new Response('', Response::HTTP_FOUND, [
            'Location' => 'https://internal.example/debug',
        ]);

        $rendered = $this->app->make(ApiExceptionRenderer::class)->render(
            $response,
            new RuntimeException('private diagnostic'),
            $request,
        );

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $rendered->getStatusCode());
        $this->assertSame('INTERNAL_ERROR', $rendered->getData(true)['error']['code']);
        $this->assertFalse($rendered->headers->has('Location'));
    }

    public function test_only_semantic_headers_survive_error_body_replacement(): void
    {
        $request = $this->bindRequestId();
        $response = new Response('', Response::HTTP_TOO_MANY_REQUESTS, [
            'Access-Control-Allow-Origin' => 'https://app.educonnect.example',
            'Allow' => 'GET',
            'Content-Encoding' => 'gzip',
            'Digest' => 'sha-256=stale',
            'ETag' => '"stale"',
            'RateLimit-Policy' => '5;w=60',
            'Retry-After' => '60',
            'Vary' => 'Origin',
            'WWW-Authenticate' => 'Bearer',
            'X-RateLimit-Limit' => '5',
        ]);

        $rendered = $this->app->make(ApiExceptionRenderer::class)->render(
            $response,
            new RuntimeException('private diagnostic'),
            $request,
        );

        $this->assertSame('https://app.educonnect.example', $rendered->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('GET', $rendered->headers->get('Allow'));
        $this->assertSame('5;w=60', $rendered->headers->get('RateLimit-Policy'));
        $this->assertSame('60', $rendered->headers->get('Retry-After'));
        $this->assertSame('Origin', $rendered->headers->get('Vary'));
        $this->assertSame('Bearer', $rendered->headers->get('WWW-Authenticate'));
        $this->assertSame('5', $rendered->headers->get('X-RateLimit-Limit'));
        $this->assertFalse($rendered->headers->has('Content-Encoding'));
        $this->assertFalse($rendered->headers->has('Digest'));
        $this->assertFalse($rendered->headers->has('ETag'));
    }

    private function bindRequestId(): Request
    {
        $request = Request::create('/api/v1/test');
        $request->attributes->set(RequestId::ATTRIBUTE, self::REQUEST_ID);
        $this->app->instance('request', $request);

        return $request;
    }
}
