<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Intake;

use App\Domains\Intake\Actions\RetryIntakeAction;
use App\Http\Requests\Api\V1\Intake\EmptyIntakeRequest;
use App\Http\Resources\IntakeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RetryIntakeItemController
{
    public function __invoke(EmptyIntakeRequest $request, string $item, RetryIntakeAction $retry): JsonResponse
    {
        $retried = $retry->execute($request->authenticatedUser(), $item);

        return ApiResponse::success(IntakeItemResource::make($retried)->resolve($request));
    }
}
