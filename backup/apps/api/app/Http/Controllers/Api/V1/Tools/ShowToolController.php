<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tools;

use App\Domains\Tools\Queries\FindPublishedTool;
use App\Http\Requests\Api\V1\Tools\EmptyToolRequest;
use App\Http\Resources\ToolResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowToolController
{
    public function __invoke(EmptyToolRequest $request, string $tool, FindPublishedTool $tools): JsonResponse
    {
        $publishedTool = $tools->execute($request->authenticatedUser(), $tool);

        return ApiResponse::success(ToolResource::make($publishedTool)->resolve($request));
    }
}
