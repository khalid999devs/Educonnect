<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Prompts;

use App\Domains\Guidance\Actions\RecordPromptCopyAction;
use App\Http\Requests\Api\V1\Guidance\EmptyGuidanceRequest;
use App\Http\Resources\PromptTemplateResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RecordPromptCopyController
{
    public function __invoke(
        EmptyGuidanceRequest $request,
        string $prompt,
        RecordPromptCopyAction $copies,
    ): JsonResponse {
        $copied = $copies->execute($request->authenticatedUser(), $prompt);

        return ApiResponse::success(PromptTemplateResource::make($copied)->resolve($request));
    }
}
