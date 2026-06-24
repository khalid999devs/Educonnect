<?php

namespace Tests\Unit\Support;

use App\Support\ApiResponse;
use Tests\TestCase;

final class ApiResponseTest extends TestCase
{
    public function test_success_response_omits_empty_metadata(): void
    {
        $response = ApiResponse::success(['status' => 'up']);

        $this->assertSame([
            'data' => [
                'status' => 'up',
            ],
        ], $response->getData(true));
    }

    public function test_success_response_includes_metadata_when_provided(): void
    {
        $response = ApiResponse::success(
            data: ['id' => 1],
            meta: ['request_id' => 'test-request'],
            status: 201,
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame([
            'data' => [
                'id' => 1,
            ],
            'meta' => [
                'request_id' => 'test-request',
            ],
        ], $response->getData(true));
    }
}
