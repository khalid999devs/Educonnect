<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Courses;

use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Users\Models\User;
use App\Http\Resources\CourseResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class ShowCourseController
{
    public function __invoke(Request $request, string $course, FindOwnedCourse $courses): JsonResponse
    {
        $resource = $courses->execute($this->user($request), $course);

        return ApiResponse::success(CourseResource::make($resource)->resolve($request));
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated EduConnect user is required.');
        }

        return $user;
    }
}
