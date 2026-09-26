<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Templates;

use App\Domains\Templates\Queries\FindPublishedTemplate;
use App\Http\Requests\Api\V1\Templates\EmptyTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowTemplateController
{
    public function __invoke(EmptyTemplateRequest $request, string $template, FindPublishedTemplate $templates): JsonResponse
    {
        $publishedTemplate = $templates->execute($request->authenticatedUser(), $template);

        return ApiResponse::success(TemplateResource::make($publishedTemplate)->resolve($request));
    }
}
