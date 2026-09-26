<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Courses;

use App\Domains\Courses\Actions\CreateAcademicTermAction;
use App\Http\Requests\Api\V1\Courses\StoreAcademicTermRequest;
use App\Http\Resources\AcademicTermResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateAcademicTermController
{
    public function __invoke(StoreAcademicTermRequest $request, CreateAcademicTermAction $create): JsonResponse
    {
        $term = $create->execute($request->authenticatedUser(), $request->termData());

        return ApiResponse::success(AcademicTermResource::make($term)->resolve($request), status: 201);
    }
}
