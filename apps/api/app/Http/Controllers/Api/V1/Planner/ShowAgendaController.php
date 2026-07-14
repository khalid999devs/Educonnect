<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Data\PlannerWindowResult;
use App\Domains\Planner\Queries\ReadPlannerWindow;
use App\Http\Requests\Api\V1\Planner\AgendaRequest;
use App\Http\Resources\FocusSessionResource;
use App\Http\Resources\TaskResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowAgendaController
{
    public function __invoke(AgendaRequest $request, ReadPlannerWindow $planner): JsonResponse
    {
        $result = $planner->execute(
            $request->authenticatedUser(),
            $request->timezone(),
            $request->anchor(),
            $request->startsAt(),
            $request->endsAt(),
            $request->limit(),
            'agenda',
        );

        return $this->response($request, $result);
    }

    private function response(AgendaRequest $request, PlannerWindowResult $result): JsonResponse
    {
        return ApiResponse::success([
            'timezone' => $result->timezone,
            'date' => $result->anchor,
            'window' => [
                'starts_at' => $result->startsAt->toISOString(),
                'ends_at' => $result->endsAt->toISOString(),
            ],
            'tasks' => TaskResource::collection($result->tasks)->resolve($request),
            'focus_sessions' => FocusSessionResource::collection($result->focusSessions)->resolve($request),
        ], [
            'summary' => $result->counts,
            'has_more' => $result->hasMore,
            'limit' => $result->limit,
        ]);
    }
}
