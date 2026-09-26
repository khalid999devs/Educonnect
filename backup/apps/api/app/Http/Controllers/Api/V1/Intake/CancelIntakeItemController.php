<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Intake;

use App\Domains\Intake\Actions\CancelIntakeAction;
use App\Http\Requests\Api\V1\Intake\EmptyIntakeRequest;
use App\Http\Resources\IntakeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CancelIntakeItemController
{
    public function __invoke(EmptyIntakeRequest $request, string $item, CancelIntakeAction $cancel): JsonResponse
    {
        $cancelled = $cancel->execute($request->authenticatedUser(), $item);

        return ApiResponse::success(IntakeItemResource::make($cancelled)->resolve($request));
    }
}
