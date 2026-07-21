<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Domains\Users\Queries\ListOwnSessions;
use App\Http\Requests\Api\V1\Settings\ListSessionsRequest;
use App\Http\Resources\SessionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListSessionsController
{
    public function __invoke(ListSessionsRequest $request, ListOwnSessions $sessions): JsonResponse
    {
        $summaries = $sessions->execute($request->authenticatedUser(), $request->currentSessionId());

        return ApiResponse::success(
            SessionResource::collection($summaries)->resolve($request),
            ['total' => count($summaries)],
        );
    }
}
