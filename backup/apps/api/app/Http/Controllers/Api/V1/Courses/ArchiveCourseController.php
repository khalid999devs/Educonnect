<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Courses;

use App\Domains\Courses\Actions\SetCourseArchiveStateAction;
use App\Http\Requests\Api\V1\Courses\VersionedMutationRequest;
use App\Http\Resources\CourseResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ArchiveCourseController
{
    public function __invoke(
        VersionedMutationRequest $request,
        string $course,
        SetCourseArchiveStateAction $archive,
    ): JsonResponse {
        $resource = $archive->execute(
            $request->authenticatedUser(),
            $course,
            archived: true,
            expectedVersion: $request->expectedVersion(),
        );

        return ApiResponse::success(CourseResource::make($resource)->resolve($request));
    }
}
