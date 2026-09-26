<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Intake;

use App\Domains\Intake\Actions\CreateLinkIntakeAction;
use App\Http\Requests\Api\V1\Intake\StoreLinkIntakeRequest;
use App\Http\Resources\IntakeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateLinkIntakeController
{
    public function __invoke(StoreLinkIntakeRequest $request, CreateLinkIntakeAction $intake): JsonResponse
    {
        $item = $intake->execute($request->authenticatedUser(), $request->url(), $request->context());

        return ApiResponse::success(IntakeItemResource::make($item)->resolve($request), status: 201);
    }
}
