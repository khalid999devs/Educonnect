<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TemplateCopies;

use App\Domains\Templates\Queries\ListTemplateCopies;
use App\Http\Requests\Api\V1\Templates\ListTemplateCopiesRequest;
use App\Http\Resources\TemplateCopyResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListTemplateCopiesController
{
    public function __invoke(ListTemplateCopiesRequest $request, ListTemplateCopies $copies): JsonResponse
    {
        $paginator = $copies->execute(
            $request->authenticatedUser(),
            $request->destination(),
            $request->coursePublicId(),
            $request->includeArchived(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            TemplateCopyResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
