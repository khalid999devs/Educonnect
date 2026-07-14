<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Actions\DeleteFocusSessionAction;
use App\Http\Requests\Api\V1\Planner\VersionedPlannerMutationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class DeleteFocusSessionController
{
    public function __invoke(
        VersionedPlannerMutationRequest $request,
        string $focus_session,
        DeleteFocusSessionAction $delete,
    ): Response {
        $delete->execute($request->authenticatedUser(), $focus_session, $request->expectedVersion());

        return ApiResponse::noContent();
    }
}
