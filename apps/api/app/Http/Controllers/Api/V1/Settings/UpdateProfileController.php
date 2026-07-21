<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Domains\Users\Actions\UpdateOwnProfileAction;
use App\Http\Requests\Api\V1\Settings\UpdateProfileRequest;
use App\Http\Resources\SettingsResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateProfileController
{
    public function __invoke(UpdateProfileRequest $request, UpdateOwnProfileAction $action): JsonResponse
    {
        $snapshot = $action->execute($request->authenticatedUser(), $request->profileData());

        return ApiResponse::success(SettingsResource::make($snapshot)->resolve($request));
    }
}
