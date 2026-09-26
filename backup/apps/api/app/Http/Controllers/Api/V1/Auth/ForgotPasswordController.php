<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\SendPasswordResetLinkAction;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ForgotPasswordController
{
    public function __invoke(
        ForgotPasswordRequest $request,
        SendPasswordResetLinkAction $sendPasswordResetLink,
    ): JsonResponse {
        $sendPasswordResetLink->execute($request->email());

        return ApiResponse::success([
            'message' => 'If an account matches that email, a password reset link will be sent.',
        ], status: 202);
    }
}
