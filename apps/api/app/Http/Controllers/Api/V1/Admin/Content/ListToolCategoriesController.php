<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content;

use App\Domains\Tools\Models\ToolCategory;
use App\Http\Resources\ToolCategoryResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListToolCategoriesController
{
    public function __invoke(Request $request): JsonResponse
    {
        $categories = ToolCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(ToolCategoryResource::collection($categories)->resolve($request));
    }
}
