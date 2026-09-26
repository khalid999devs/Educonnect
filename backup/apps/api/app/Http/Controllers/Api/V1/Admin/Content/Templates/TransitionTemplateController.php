<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Templates;

use App\Domains\Content\Actions\TransitionContentAction;
use App\Domains\Templates\Models\Template;
use App\Http\Requests\Api\V1\Admin\Content\TransitionContentRequest;
use App\Http\Resources\AdminTemplateResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TransitionTemplateController
{
    public function __invoke(TransitionContentRequest $request, Template $template, TransitionContentAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->adminUser(),
            $template,
            'template',
            $request->transition(),
            $request->expectedVersion(),
            $request->reason(),
            $request->requestId(),
        );

        return ApiResponse::success(AdminTemplateResource::make($result->load(['category', 'latestVersion']))->resolve($request));
    }
}
