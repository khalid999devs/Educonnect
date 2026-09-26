<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Templates;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Templates\Actions\ClearTemplatePreferenceAction;
use App\Http\Requests\Api\V1\Templates\EmptyTemplateRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class UndismissTemplateController
{
    public function __invoke(
        EmptyTemplateRequest $request,
        string $template,
        ClearTemplatePreferenceAction $preferences,
    ): Response {
        $preferences->execute($request->authenticatedUser(), $template, GuidancePreferenceState::Dismissed);

        return ApiResponse::noContent();
    }
}
