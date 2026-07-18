<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Community;

use App\Domains\Community\Actions\SetCommunityVisibilityAction;
use App\Domains\Community\Models\Community;
use App\Http\Requests\Api\V1\Admin\Community\SetCommunityVisibilityRequest;
use App\Http\Resources\AdminCommunityResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SetCommunityVisibilityController
{
    public function __invoke(SetCommunityVisibilityRequest $request, Community $community, SetCommunityVisibilityAction $action): JsonResponse
    {
        $updated = $action->execute(
            $request->adminUser(),
            $community,
            $request->visibility(),
            $request->reason(),
            $request->requestId(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(AdminCommunityResource::make($updated->loadCount('memberships'))->resolve($request));
    }
}
