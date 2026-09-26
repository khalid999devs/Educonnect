<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Templates;

use App\Domains\Templates\Queries\ListTemplatesForAdmin;
use App\Http\Requests\Api\V1\Admin\Content\ListAdminContentRequest;
use App\Http\Resources\AdminTemplateResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListTemplatesController
{
    public function __invoke(ListAdminContentRequest $request, ListTemplatesForAdmin $templates): JsonResponse
    {
        $paginator = $templates->execute($request->state(), $request->perPage());

        return ApiResponse::collection(
            AdminTemplateResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
