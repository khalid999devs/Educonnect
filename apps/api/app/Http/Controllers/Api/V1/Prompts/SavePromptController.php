<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Prompts;

use App\Domains\Guidance\Actions\SetPromptPreferenceAction;
use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Http\Requests\Api\V1\Guidance\EmptyGuidanceRequest;
use App\Http\Resources\PromptTemplateResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SavePromptController
{
    public function __invoke(
        EmptyGuidanceRequest $request,
        string $prompt,
        SetPromptPreferenceAction $preferences,
    ): JsonResponse {
        $saved = $preferences->execute($request->authenticatedUser(), $prompt, GuidancePreferenceState::Saved);

        return ApiResponse::success(PromptTemplateResource::make($saved)->resolve($request));
    }
}
