<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Tools;

use App\Domains\Content\Actions\TransitionContentAction;
use App\Domains\Tools\Models\Tool;
use App\Http\Requests\Api\V1\Admin\Content\TransitionContentRequest;
use App\Http\Resources\AdminToolResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TransitionToolController
{
    public function __invoke(TransitionContentRequest $request, Tool $tool, TransitionContentAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->adminUser(),
            $tool,
            'tool',
            $request->transition(),
            $request->expectedVersion(),
            $request->reason(),
            $request->requestId(),
        );

        return ApiResponse::success(AdminToolResource::make($result->load('category'))->resolve($request));
    }
}
