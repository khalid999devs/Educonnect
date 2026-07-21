<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Domains\Users\Queries\BuildOwnSettings;
use App\Http\Requests\Api\V1\Settings\ShowSettingsRequest;
use App\Http\Resources\SettingsResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowSettingsController
{
    public function __invoke(ShowSettingsRequest $request, BuildOwnSettings $settings): JsonResponse
    {
        $snapshot = $settings->execute($request->authenticatedUser());

        return ApiResponse::success(SettingsResource::make($snapshot)->resolve($request));
    }
}
