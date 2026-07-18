<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Users;

use App\Domains\Users\Models\User;
use App\Http\Resources\AdminUserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowUserController
{
    public function __invoke(Request $request, User $user): JsonResponse
    {
        return ApiResponse::success(AdminUserResource::make($user->load('roles'))->resolve($request));
    }
}
