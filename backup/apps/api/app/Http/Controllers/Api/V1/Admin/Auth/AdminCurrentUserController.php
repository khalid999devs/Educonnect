<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Domains\Users\Models\User;
use App\Http\Resources\AdminSessionResource;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminCurrentUserController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user('admin');

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.', ['admin']);
        }

        return ApiResponse::success(AdminSessionResource::make($user));
    }
}
