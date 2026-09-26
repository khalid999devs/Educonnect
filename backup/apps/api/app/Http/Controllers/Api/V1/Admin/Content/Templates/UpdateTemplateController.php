<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Templates;

use App\Domains\Templates\Actions\UpdateTemplateAction;
use App\Domains\Templates\Models\Template;
use App\Http\Requests\Api\V1\Admin\Content\UpdateTemplateRequest;
use App\Http\Resources\AdminTemplateResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateTemplateController
{
    public function __invoke(UpdateTemplateRequest $request, Template $template, UpdateTemplateAction $action): JsonResponse
    {
        $updated = $action->execute($request->adminUser(), $template, $request->contentData(), $request->expectedVersion());

        return ApiResponse::success(AdminTemplateResource::make($updated)->resolve($request));
    }
}
