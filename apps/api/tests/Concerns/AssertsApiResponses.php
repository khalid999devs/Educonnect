<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Support\ApiErrorCode;
use App\Support\RequestId;
use Illuminate\Testing\TestResponse;

trait AssertsApiResponses
{
    protected function assertSuccessRequestId(TestResponse $response): string
    {
        return $this->assertRequestIdAt($response, 'meta.request_id');
    }

    protected function assertApiError(TestResponse $response, int $status, ApiErrorCode $code): string
    {
        $response
            ->assertStatus($status)
            ->assertJsonPath('error.code', $code->value)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'request_id',
                ],
            ])
            ->assertJsonMissingPath('message')
            ->assertJsonMissingPath('errors')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line')
            ->assertJsonMissingPath('trace');

        return $this->assertRequestIdAt($response, 'error.request_id');
    }

    private function assertRequestIdAt(TestResponse $response, string $path): string
    {
        $requestId = $response->headers->get(RequestId::HEADER);

        $this->assertIsString($requestId);
        $this->assertMatchesRegularExpression(RequestId::PATTERN, $requestId);
        $this->assertSame($requestId, $response->json($path));

        return $requestId;
    }
}
