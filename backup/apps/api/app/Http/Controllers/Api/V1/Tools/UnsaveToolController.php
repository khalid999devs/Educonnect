<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tools;

use App\Domains\Tools\Actions\ClearToolPreferenceAction;
use App\Domains\Tools\Enums\ToolPreferenceState;
use App\Http\Requests\Api\V1\Tools\EmptyToolRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class UnsaveToolController
{
    public function __invoke(
        EmptyToolRequest $request,
        string $tool,
        ClearToolPreferenceAction $preferences,
    ): Response {
        $preferences->execute($request->authenticatedUser(), $tool, ToolPreferenceState::Saved);

        return ApiResponse::noContent();
    }
}
