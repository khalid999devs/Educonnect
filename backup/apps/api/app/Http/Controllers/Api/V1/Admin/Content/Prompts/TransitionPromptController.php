<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Prompts;

use App\Domains\Content\Actions\TransitionContentAction;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Http\Requests\Api\V1\Admin\Content\TransitionContentRequest;
use App\Http\Resources\AdminPromptResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TransitionPromptController
{
    public function __invoke(TransitionContentRequest $request, PromptTemplate $prompt, TransitionContentAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->adminUser(),
            $prompt,
            'prompt_template',
            $request->transition(),
            $request->expectedVersion(),
            $request->reason(),
            $request->requestId(),
        );

        return ApiResponse::success(
            AdminPromptResource::make($result->load(['category', 'relatedTools']))->resolve($request),
        );
    }
}
