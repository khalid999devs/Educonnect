<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Domains\Onboarding\Queries\GetOnboardingSnapshot;
use App\Domains\Users\Models\User;
use App\Http\Resources\OnboardingResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class ShowOnboardingController
{
    public function __invoke(Request $request, GetOnboardingSnapshot $getOnboardingSnapshot): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated EduConnect user is required.');
        }

        return ApiResponse::success([
            'onboarding' => OnboardingResource::make($getOnboardingSnapshot->forUser($user)),
        ]);
    }
}
