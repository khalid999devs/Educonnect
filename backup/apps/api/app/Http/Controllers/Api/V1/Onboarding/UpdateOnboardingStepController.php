<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Domains\Onboarding\Actions\UpdateOnboardingStepAction;
use App\Domains\Onboarding\Enums\OnboardingStep;
use App\Http\Requests\Api\V1\Onboarding\UpdateOnboardingStepRequest;
use App\Http\Resources\OnboardingResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateOnboardingStepController
{
    public function __invoke(
        UpdateOnboardingStepRequest $request,
        OnboardingStep $step,
        UpdateOnboardingStepAction $updateOnboardingStep,
    ): JsonResponse {
        $snapshot = $updateOnboardingStep->execute(
            $request->authenticatedUser(),
            $step,
            $request->stepPayload(),
            $request->expectedVersion(),
        );

        return ApiResponse::success([
            'onboarding' => OnboardingResource::make($snapshot),
        ]);
    }
}
