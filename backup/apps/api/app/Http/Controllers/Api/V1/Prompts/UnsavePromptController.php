<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Prompts;

use App\Domains\Guidance\Actions\ClearPromptPreferenceAction;
use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Http\Requests\Api\V1\Guidance\EmptyGuidanceRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class UnsavePromptController
{
    public function __invoke(
        EmptyGuidanceRequest $request,
        string $prompt,
        ClearPromptPreferenceAction $preferences,
    ): Response {
        $preferences->execute($request->authenticatedUser(), $prompt, GuidancePreferenceState::Saved);

        return ApiResponse::noContent();
    }
}
