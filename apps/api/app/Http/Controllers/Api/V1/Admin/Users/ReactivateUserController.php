<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Users;

use App\Domains\Users\Actions\ReactivateUserAction;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Admin\ReactivateUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ReactivateUserController
{
    public function __invoke(ReactivateUserRequest $request, User $user, ReactivateUserAction $action): JsonResponse
    {
        $reactivated = $action->execute(
            $request->adminUser(),
            $user,
            $request->reason(),
            $request->requestId(),
        );

        return ApiResponse::success(AdminUserResource::make($reactivated->load('roles'))->resolve($request));
    }
}
