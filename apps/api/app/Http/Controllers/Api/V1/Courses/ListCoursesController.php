<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Courses;

use App\Domains\Courses\Queries\ListOwnedCourses;
use App\Http\Requests\Api\V1\Courses\ListCoursesRequest;
use App\Http\Resources\CourseResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListCoursesController
{
    public function __invoke(ListCoursesRequest $request, ListOwnedCourses $courses): JsonResponse
    {
        $result = $courses->execute(
            $request->authenticatedUser(),
            $request->search(),
            $request->status(),
            $request->termId(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            CourseResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
