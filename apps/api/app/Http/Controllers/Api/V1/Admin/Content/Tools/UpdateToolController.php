<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Tools;

use App\Domains\Tools\Actions\UpdateToolAction;
use App\Domains\Tools\Models\Tool;
use App\Http\Requests\Api\V1\Admin\Content\UpdateToolRequest;
use App\Http\Resources\AdminToolResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateToolController
{
    public function __invoke(UpdateToolRequest $request, Tool $tool, UpdateToolAction $action): JsonResponse
    {
        $updated = $action->execute($request->adminUser(), $tool, $request->contentData(), $request->expectedVersion());

        return ApiResponse::success(AdminToolResource::make($updated)->resolve($request));
    }
}
