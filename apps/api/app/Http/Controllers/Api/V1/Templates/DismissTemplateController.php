<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Templates;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Templates\Actions\SetTemplatePreferenceAction;
use App\Http\Requests\Api\V1\Templates\EmptyTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class DismissTemplateController
{
    public function __invoke(
        EmptyTemplateRequest $request,
        string $template,
        SetTemplatePreferenceAction $preferences,
    ): JsonResponse {
        $dismissed = $preferences->execute($request->authenticatedUser(), $template, GuidancePreferenceState::Dismissed);

        return ApiResponse::success(TemplateResource::make($dismissed)->resolve($request));
    }
}
