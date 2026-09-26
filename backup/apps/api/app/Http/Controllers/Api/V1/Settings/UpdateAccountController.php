<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Domains\Users\Actions\UpdateOwnAccountAction;
use App\Http\Requests\Api\V1\Settings\UpdateAccountRequest;
use App\Http\Resources\SettingsResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateAccountController
{
    public function __invoke(UpdateAccountRequest $request, UpdateOwnAccountAction $action): JsonResponse
    {
        $snapshot = $action->execute($request->authenticatedUser(), $request->accountData());

        return ApiResponse::success(SettingsResource::make($snapshot)->resolve($request));
    }
}
