<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Courses;

use App\Domains\Courses\Queries\ListOwnedAcademicTerms;
use App\Http\Requests\Api\V1\Courses\ListAcademicTermsRequest;
use App\Http\Resources\AcademicTermResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListAcademicTermsController
{
    public function __invoke(ListAcademicTermsRequest $request, ListOwnedAcademicTerms $terms): JsonResponse
    {
        $paginator = $terms->execute(
            $request->authenticatedUser(),
            $request->search(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            AcademicTermResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
