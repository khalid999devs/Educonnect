<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Domains\Guidance\Actions\ClearWorkflowPreferenceAction;
use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Http\Requests\Api\V1\Guidance\EmptyGuidanceRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class UnsaveWorkflowController
{
    public function __invoke(
        EmptyGuidanceRequest $request,
        string $workflow,
        ClearWorkflowPreferenceAction $preferences,
    ): Response {
        $preferences->execute($request->authenticatedUser(), $workflow, GuidancePreferenceState::Saved);

        return ApiResponse::noContent();
    }
}
