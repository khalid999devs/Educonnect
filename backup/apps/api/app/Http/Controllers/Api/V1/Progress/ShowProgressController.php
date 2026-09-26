<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Progress;

use App\Domains\Progress\Queries\BuildProgressOverview;
use App\Http\Requests\Api\V1\Progress\ShowProgressRequest;
use App\Http\Resources\ProgressResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowProgressController
{
    public function __invoke(ShowProgressRequest $request, BuildProgressOverview $overview): JsonResponse
    {
        $progress = $overview->execute($request->authenticatedUser(), $request->timezone(), $request->window());

        return ApiResponse::success(ProgressResource::make($progress)->resolve($request));
    }
}
