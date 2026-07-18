<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Templates;

use App\Domains\Templates\Actions\CreateTemplateAction;
use App\Http\Requests\Api\V1\Admin\Content\CreateTemplateRequest;
use App\Http\Resources\AdminTemplateResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateTemplateController
{
    public function __invoke(CreateTemplateRequest $request, CreateTemplateAction $action): JsonResponse
    {
        $template = $action->execute($request->adminUser(), $request->contentData());

        return ApiResponse::success(AdminTemplateResource::make($template)->resolve($request), [], 201);
    }
}
