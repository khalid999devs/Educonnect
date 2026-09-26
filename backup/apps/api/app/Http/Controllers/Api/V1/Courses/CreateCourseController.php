<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Courses;

use App\Domains\Courses\Actions\CreateCourseAction;
use App\Http\Requests\Api\V1\Courses\StoreCourseRequest;
use App\Http\Resources\CourseResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateCourseController
{
    public function __invoke(StoreCourseRequest $request, CreateCourseAction $create): JsonResponse
    {
        $course = $create->execute($request->authenticatedUser(), $request->courseData());

        return ApiResponse::success(CourseResource::make($course)->resolve($request), status: 201);
    }
}
