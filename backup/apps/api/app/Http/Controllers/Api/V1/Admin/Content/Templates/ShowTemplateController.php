<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Templates;

use App\Domains\Templates\Models\Template;
use App\Http\Resources\AdminTemplateResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowTemplateController
{
    public function __invoke(Request $request, Template $template): JsonResponse
    {
        return ApiResponse::success(
            AdminTemplateResource::make($template->load(['category', 'latestVersion']))->resolve($request),
        );
    }
}
