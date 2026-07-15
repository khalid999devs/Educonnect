<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Prompts;

use App\Domains\Guidance\Queries\FindPublishedPrompt;
use App\Http\Requests\Api\V1\Guidance\EmptyGuidanceRequest;
use App\Http\Resources\PromptTemplateResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowPromptController
{
    public function __invoke(EmptyGuidanceRequest $request, string $prompt, FindPublishedPrompt $prompts): JsonResponse
    {
        $publishedPrompt = $prompts->execute($request->authenticatedUser(), $prompt);

        return ApiResponse::success(PromptTemplateResource::make($publishedPrompt)->resolve($request));
    }
}
