<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Courses;

use App\Domains\Courses\Actions\UpdateAcademicTermAction;
use App\Http\Requests\Api\V1\Courses\UpdateAcademicTermRequest;
use App\Http\Resources\AcademicTermResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateAcademicTermController
{
    public function __invoke(
        UpdateAcademicTermRequest $request,
        string $term,
        UpdateAcademicTermAction $update,
    ): JsonResponse {
        $resource = $update->execute(
            $request->authenticatedUser(),
            $term,
            $request->termData(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(AcademicTermResource::make($resource)->resolve($request));
    }
}
