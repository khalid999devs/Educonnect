<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Prompts;

use App\Domains\Guidance\Queries\ListPublishedPrompts;
use App\Http\Requests\Api\V1\Guidance\ListPromptsRequest;
use App\Http\Resources\PromptTemplateResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListPromptsController
{
    public function __invoke(ListPromptsRequest $request, ListPublishedPrompts $prompts): JsonResponse
    {
        $paginator = $prompts->execute(
            $request->authenticatedUser(),
            $request->search(),
            $request->category(),
            $request->preference(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            PromptTemplateResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
