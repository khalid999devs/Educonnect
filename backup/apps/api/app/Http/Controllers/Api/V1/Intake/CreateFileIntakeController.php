<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Intake;

use App\Domains\Intake\Actions\CreateFileIntakeAction;
use App\Http\Requests\Api\V1\Intake\StoreFileIntakeRequest;
use App\Http\Resources\IntakeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateFileIntakeController
{
    public function __invoke(StoreFileIntakeRequest $request, CreateFileIntakeAction $intake): JsonResponse
    {
        $item = $intake->execute($request->authenticatedUser(), $request->resourcePublicId(), $request->context());

        return ApiResponse::success(IntakeItemResource::make($item)->resolve($request), status: 201);
    }
}
