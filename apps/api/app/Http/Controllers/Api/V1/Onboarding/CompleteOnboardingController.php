<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Domains\Onboarding\Actions\CompleteOnboardingAction;
use App\Http\Requests\Api\V1\Onboarding\CompleteOnboardingRequest;
use App\Http\Resources\OnboardingResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CompleteOnboardingController
{
    public function __invoke(
        CompleteOnboardingRequest $request,
        CompleteOnboardingAction $completeOnboarding,
    ): JsonResponse {
        $snapshot = $completeOnboarding->execute(
            $request->authenticatedUser(),
            $request->expectedVersion(),
        );

        return ApiResponse::success([
            'onboarding' => OnboardingResource::make($snapshot),
        ]);
    }
}
