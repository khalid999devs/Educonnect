<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\SendEmailVerificationAction;
use App\Http\Requests\Api\V1\Auth\SendEmailVerificationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SendEmailVerificationController
{
    public function __invoke(
        SendEmailVerificationRequest $request,
        SendEmailVerificationAction $sendEmailVerification,
    ): JsonResponse {
        $sendEmailVerification->execute($request->authenticatedUser());

        return ApiResponse::success([
            'message' => 'If email verification is required, a link will be sent.',
        ], status: 202);
    }
}
