<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Courses;

use App\Domains\Courses\Actions\UpdateCourseAction;
use App\Http\Requests\Api\V1\Courses\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateCourseController
{
    public function __invoke(UpdateCourseRequest $request, string $course, UpdateCourseAction $update): JsonResponse
    {
        $resource = $update->execute(
            $request->authenticatedUser(),
            $course,
            $request->courseData(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(CourseResource::make($resource)->resolve($request));
    }
}
