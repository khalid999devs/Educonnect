<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Users;

use App\Domains\Users\Actions\SuspendUserAction;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Admin\SuspendUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SuspendUserController
{
    public function __invoke(SuspendUserRequest $request, User $user, SuspendUserAction $action): JsonResponse
    {
        $suspended = $action->execute(
            $request->adminUser(),
            $user,
            $request->reason(),
            $request->requestId(),
        );

        return ApiResponse::success(AdminUserResource::make($suspended->load('roles'))->resolve($request));
    }
}
