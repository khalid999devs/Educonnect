<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Users;

use App\Domains\Users\Queries\ListUsers;
use App\Http\Requests\Api\V1\Admin\ListUsersRequest;
use App\Http\Resources\AdminUserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListUsersController
{
    public function __invoke(ListUsersRequest $request, ListUsers $users): JsonResponse
    {
        $paginator = $users->execute(
            $request->search(),
            $request->role(),
            $request->status(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            AdminUserResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
