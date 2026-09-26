<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Courses;

use App\Domains\Courses\Actions\DeleteCourseAction;
use App\Http\Requests\Api\V1\Courses\VersionedMutationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class DeleteCourseController
{
    public function __invoke(
        VersionedMutationRequest $request,
        string $course,
        DeleteCourseAction $delete,
    ): Response {
        $delete->execute($request->authenticatedUser(), $course, $request->expectedVersion());

        return ApiResponse::noContent();
    }
}
