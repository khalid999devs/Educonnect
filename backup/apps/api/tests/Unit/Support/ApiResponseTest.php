<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ApiErrorCode;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ApiResponseTest extends TestCase
{
    private const REQUEST_ID = 'req_0123456789abcdef0123456789abcdef';

    public function test_success_response_always_includes_request_metadata_and_header(): void
    {
        $this->bindRequestId();

        $response = ApiResponse::success(['status' => 'up']);

        $this->assertSame([
            'data' => [
                'status' => 'up',
            ],
            'meta' => [
                'request_id' => self::REQUEST_ID,
            ],
        ], $response->getData(true));
        $this->assertSame(self::REQUEST_ID, $response->headers->get(RequestId::HEADER));
    }

    public function test_server_request_id_cannot_be_overridden_by_caller_metadata(): void
    {
        $this->bindRequestId();

        $response = ApiResponse::success(
            data: ['id' => 1],
            meta: [
                'request_id' => 'client-value',
                'pagination' => ['per_page' => 20],
            ],
            status: 201,
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(self::REQUEST_ID, $response->getData(true)['meta']['request_id']);
        $this->assertSame(['per_page' => 20], $response->getData(true)['meta']['pagination']);
    }

    public function test_error_response_uses_the_standard_envelope(): void
    {
        $this->bindRequestId();

        $response = ApiResponse::error(
            code: ApiErrorCode::ValidationFailed,
            message: 'Some fields need attention.',
            status: 422,
            details: [
                'fields' => [
                    'title' => ['The title field is required.'],
                ],
            ],
        );

        $this->assertSame([
            'error' => [
                'code' => 'VALIDATION_FAILED',
                'message' => 'Some fields need attention.',
                'details' => [
                    'fields' => [
                        'title' => ['The title field is required.'],
                    ],
                ],
                'request_id' => self::REQUEST_ID,
            ],
        ], $response->getData(true));
    }

    private function bindRequestId(): void
    {
        $request = Request::create('/api/v1/test');
        $request->attributes->set(RequestId::ATTRIBUTE, self::REQUEST_ID);
        $this->app->instance('request', $request);
    }
}
