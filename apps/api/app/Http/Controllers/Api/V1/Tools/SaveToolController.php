<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tools;

use App\Domains\Tools\Actions\SetToolPreferenceAction;
use App\Domains\Tools\Enums\ToolPreferenceState;
use App\Http\Requests\Api\V1\Tools\EmptyToolRequest;
use App\Http\Resources\ToolResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SaveToolController
{
    public function __invoke(
        EmptyToolRequest $request,
        string $tool,
        SetToolPreferenceAction $preferences,
    ): JsonResponse {
        $saved = $preferences->execute($request->authenticatedUser(), $tool, ToolPreferenceState::Saved);

        return ApiResponse::success(ToolResource::make($saved)->resolve($request));
    }
}
