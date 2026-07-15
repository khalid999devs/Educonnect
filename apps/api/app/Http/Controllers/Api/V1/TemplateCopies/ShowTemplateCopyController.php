<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TemplateCopies;

use App\Domains\Templates\Queries\FindOwnedTemplateCopy;
use App\Http\Requests\Api\V1\Templates\EmptyTemplateRequest;
use App\Http\Resources\TemplateCopyResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowTemplateCopyController
{
    public function __invoke(EmptyTemplateRequest $request, string $copy, FindOwnedTemplateCopy $copies): JsonResponse
    {
        $ownedCopy = $copies->execute($request->authenticatedUser(), $copy);

        return ApiResponse::success(TemplateCopyResource::make($ownedCopy)->resolve($request));
    }
}
