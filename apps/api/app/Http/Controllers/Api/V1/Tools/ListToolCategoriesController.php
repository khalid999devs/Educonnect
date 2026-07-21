<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tools;

use App\Domains\Tools\Queries\ListToolCategories;
use App\Http\Requests\Api\V1\Tools\ListToolCategoriesRequest;
use App\Http\Resources\ToolCategorySummaryResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListToolCategoriesController
{
    public function __invoke(ListToolCategoriesRequest $request, ListToolCategories $categories): JsonResponse
    {
        $result = $categories->execute($request->authenticatedUser());

        return ApiResponse::success(ToolCategorySummaryResource::collection($result)->resolve($request));
    }
}
