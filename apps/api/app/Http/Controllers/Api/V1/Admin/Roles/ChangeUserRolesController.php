<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Roles;

use App\Domains\Authorization\Actions\ChangeUserRolesAction;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Admin\ChangeUserRolesRequest;
use App\Http\Resources\AdminUserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ChangeUserRolesController
{
    public function __invoke(ChangeUserRolesRequest $request, User $user, ChangeUserRolesAction $action): JsonResponse
    {
        $updated = $action->execute(
            $user,
            $request->roles(),
            $request->adminUser(),
            $request->reason(),
            $request->requestId(),
        );

        return ApiResponse::success(AdminUserResource::make($updated->load('roles'))->resolve($request));
    }
}
