<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tools;

use App\Domains\Tools\Queries\ListPublishedTools;
use App\Http\Requests\Api\V1\Tools\ListToolsRequest;
use App\Http\Resources\ToolResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListToolsController
{
    public function __invoke(ListToolsRequest $request, ListPublishedTools $tools): JsonResponse
    {
        $paginator = $tools->execute(
            $request->authenticatedUser(),
            $request->search(),
            $request->category(),
            $request->preference(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            ToolResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
