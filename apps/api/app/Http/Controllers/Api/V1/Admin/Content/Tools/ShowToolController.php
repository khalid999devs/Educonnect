<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Tools;

use App\Domains\Tools\Models\Tool;
use App\Http\Resources\AdminToolResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowToolController
{
    public function __invoke(Request $request, Tool $tool): JsonResponse
    {
        return ApiResponse::success(AdminToolResource::make($tool->load('category'))->resolve($request));
    }
}
