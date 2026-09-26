<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Tools;

use App\Domains\Tools\Actions\CreateToolAction;
use App\Http\Requests\Api\V1\Admin\Content\CreateToolRequest;
use App\Http\Resources\AdminToolResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateToolController
{
    public function __invoke(CreateToolRequest $request, CreateToolAction $action): JsonResponse
    {
        $tool = $action->execute($request->adminUser(), $request->contentData());

        return ApiResponse::success(AdminToolResource::make($tool)->resolve($request), [], 201);
    }
}
