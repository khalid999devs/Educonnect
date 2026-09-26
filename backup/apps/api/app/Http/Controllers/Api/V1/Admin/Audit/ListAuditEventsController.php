<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Audit;

use App\Domains\Audit\Queries\ListAuditEvents;
use App\Http\Requests\Api\V1\Admin\ListAuditEventsRequest;
use App\Http\Resources\AuditEventResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListAuditEventsController
{
    public function __invoke(ListAuditEventsRequest $request, ListAuditEvents $events): JsonResponse
    {
        $paginator = $events->execute(
            $request->action(),
            $request->actorPublicId(),
            $request->subjectType(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            AuditEventResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
