<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Templates;

use App\Domains\Templates\Queries\ListPublishedTemplates;
use App\Http\Requests\Api\V1\Templates\ListTemplatesRequest;
use App\Http\Resources\TemplateResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListTemplatesController
{
    public function __invoke(ListTemplatesRequest $request, ListPublishedTemplates $templates): JsonResponse
    {
        $paginator = $templates->execute(
            $request->authenticatedUser(),
            $request->search(),
            $request->category(),
            $request->preference(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            TemplateResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
