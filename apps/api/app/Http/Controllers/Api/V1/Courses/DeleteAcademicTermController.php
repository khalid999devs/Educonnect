<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Courses;

use App\Domains\Courses\Actions\DeleteAcademicTermAction;
use App\Http\Requests\Api\V1\Courses\VersionedMutationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class DeleteAcademicTermController
{
    public function __invoke(
        VersionedMutationRequest $request,
        string $term,
        DeleteAcademicTermAction $delete,
    ): Response {
        $delete->execute($request->authenticatedUser(), $term, $request->expectedVersion());

        return ApiResponse::noContent();
    }
}
