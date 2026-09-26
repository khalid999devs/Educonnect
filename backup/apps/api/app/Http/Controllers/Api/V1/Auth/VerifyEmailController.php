<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\VerifyUserEmailAction;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Auth\VerifyEmailRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class VerifyEmailController
{
    public function __invoke(
        VerifyEmailRequest $request,
        User $user,
        VerifyUserEmailAction $verifyUserEmail,
    ): JsonResponse {
        $verifyUserEmail->execute($user);

        return ApiResponse::success([
            'user' => UserResource::make($user->refresh()),
        ]);
    }
}
