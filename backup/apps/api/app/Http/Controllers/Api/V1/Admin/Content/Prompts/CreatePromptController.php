<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Prompts;

use App\Domains\Guidance\Actions\CreatePromptAction;
use App\Http\Requests\Api\V1\Admin\Content\CreatePromptRequest;
use App\Http\Resources\AdminPromptResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreatePromptController
{
    public function __invoke(CreatePromptRequest $request, CreatePromptAction $action): JsonResponse
    {
        $prompt = $action->execute($request->adminUser(), $request->contentData());

        return ApiResponse::success(AdminPromptResource::make($prompt)->resolve($request), [], 201);
    }
}
