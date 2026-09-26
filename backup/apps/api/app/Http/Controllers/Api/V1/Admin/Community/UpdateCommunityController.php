<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Community;

use App\Domains\Community\Actions\UpdateCommunityAction;
use App\Domains\Community\Models\Community;
use App\Http\Requests\Api\V1\Admin\Community\UpdateCommunityRequest;
use App\Http\Resources\AdminCommunityResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateCommunityController
{
    public function __invoke(UpdateCommunityRequest $request, Community $community, UpdateCommunityAction $action): JsonResponse
    {
        $updated = $action->execute($request->adminUser(), $community, $request->contentData(), $request->expectedVersion());

        return ApiResponse::success(AdminCommunityResource::make($updated->loadCount('memberships'))->resolve($request));
    }
}
