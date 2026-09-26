<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Prompts;

use App\Domains\Guidance\Queries\ListPromptsForAdmin;
use App\Http\Requests\Api\V1\Admin\Content\ListAdminContentRequest;
use App\Http\Resources\AdminPromptResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListPromptsController
{
    public function __invoke(ListAdminContentRequest $request, ListPromptsForAdmin $prompts): JsonResponse
    {
        $paginator = $prompts->execute($request->state(), $request->perPage());

        return ApiResponse::collection(
            AdminPromptResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
