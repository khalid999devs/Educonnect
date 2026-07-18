<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Community;

use App\Domains\Community\Actions\CreateCommunityAction;
use App\Http\Requests\Api\V1\Admin\Community\CreateCommunityRequest;
use App\Http\Resources\AdminCommunityResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateCommunityController
{
    public function __invoke(CreateCommunityRequest $request, CreateCommunityAction $action): JsonResponse
    {
        $community = $action->execute($request->adminUser(), $request->contentData());

        return ApiResponse::success(AdminCommunityResource::make($community)->resolve($request), [], 201);
    }
}
