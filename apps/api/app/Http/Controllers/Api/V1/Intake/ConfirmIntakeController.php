<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Intake;

use App\Domains\Intake\Actions\ConfirmIntakeAction;
use App\Http\Requests\Api\V1\Intake\ConfirmIntakeRequest;
use App\Http\Resources\IntakeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ConfirmIntakeController
{
    public function __invoke(
        ConfirmIntakeRequest $request,
        string $item,
        ConfirmIntakeAction $confirm,
    ): JsonResponse {
        $confirmed = $confirm->execute($request->authenticatedUser(), $item, $request->decisions());

        return ApiResponse::success(IntakeItemResource::make($confirmed)->resolve($request));
    }
}
