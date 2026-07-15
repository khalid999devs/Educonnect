<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Guidance;

use App\Domains\Guidance\Queries\BuildCategoryGuidance;
use App\Http\Requests\Api\V1\Guidance\ShowGuidanceRequest;
use App\Http\Resources\PromptTemplateResource;
use App\Http\Resources\ToolResource;
use App\Http\Resources\WorkflowRecipeResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowGuidanceController
{
    public function __invoke(ShowGuidanceRequest $request, BuildCategoryGuidance $guidance): JsonResponse
    {
        $result = $guidance->execute($request->authenticatedUser(), $request->categorySlug());
        $category = $result['category'];

        return ApiResponse::success([
            'category' => [
                'key' => (string) $category->slug,
                'name' => (string) $category->name,
                'description' => $category->description,
            ],
            'tools' => ToolResource::collection($result['tools'])->resolve($request),
            'prompts' => PromptTemplateResource::collection($result['prompts'])->resolve($request),
            'workflows' => WorkflowRecipeResource::collection($result['workflows'])->resolve($request),
        ]);
    }
}
