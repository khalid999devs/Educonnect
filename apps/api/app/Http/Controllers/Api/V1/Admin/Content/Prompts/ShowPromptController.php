<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Prompts;

use App\Domains\Guidance\Models\PromptTemplate;
use App\Http\Resources\AdminPromptResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowPromptController
{
    public function __invoke(Request $request, PromptTemplate $prompt): JsonResponse
    {
        return ApiResponse::success(
            AdminPromptResource::make($prompt->load(['category', 'relatedTools']))->resolve($request),
        );
    }
}
