<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Domains\Guidance\Actions\SetWorkflowPreferenceAction;
use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Http\Requests\Api\V1\Guidance\EmptyGuidanceRequest;
use App\Http\Resources\WorkflowRecipeResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class DismissWorkflowController
{
    public function __invoke(
        EmptyGuidanceRequest $request,
        string $workflow,
        SetWorkflowPreferenceAction $preferences,
    ): JsonResponse {
        $dismissed = $preferences->execute($request->authenticatedUser(), $workflow, GuidancePreferenceState::Dismissed);

        return ApiResponse::success(WorkflowRecipeResource::make($dismissed)->resolve($request));
    }
}
