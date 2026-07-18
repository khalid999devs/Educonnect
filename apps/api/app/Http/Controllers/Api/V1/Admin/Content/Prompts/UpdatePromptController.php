<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Prompts;

use App\Domains\Guidance\Actions\UpdatePromptAction;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Http\Requests\Api\V1\Admin\Content\UpdatePromptRequest;
use App\Http\Resources\AdminPromptResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdatePromptController
{
    public function __invoke(UpdatePromptRequest $request, PromptTemplate $prompt, UpdatePromptAction $action): JsonResponse
    {
        $updated = $action->execute($request->adminUser(), $prompt, $request->contentData(), $request->expectedVersion());

        return ApiResponse::success(AdminPromptResource::make($updated)->resolve($request));
    }
}
