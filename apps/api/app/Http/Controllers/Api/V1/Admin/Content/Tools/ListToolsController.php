<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Tools;

use App\Domains\Tools\Queries\ListToolsForAdmin;
use App\Http\Requests\Api\V1\Admin\Content\ListAdminContentRequest;
use App\Http\Resources\AdminToolResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListToolsController
{
    public function __invoke(ListAdminContentRequest $request, ListToolsForAdmin $tools): JsonResponse
    {
        $paginator = $tools->execute($request->state(), $request->perPage());

        return ApiResponse::collection(
            AdminToolResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
