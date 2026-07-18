<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Roles;

use App\Domains\Authorization\Models\Role;
use App\Http\Resources\RoleResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListRolesController
{
    public function __invoke(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->with('capabilities')
            ->orderBy('display_priority')
            ->get();

        return ApiResponse::success(RoleResource::collection($roles)->resolve($request));
    }
}
