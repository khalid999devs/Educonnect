<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tools;

use App\Domains\Tools\Queries\RankToolsForScenario;
use App\Http\Requests\Api\V1\Tools\ScenarioSearchRequest;
use App\Http\Resources\ScenarioSearchResultResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ScenarioSearchController
{
    private const DISCLAIMER = 'AI-assisted ranking of curated tools. Check each tool against your own needs, budget, and course rules before using it.';

    public function __invoke(ScenarioSearchRequest $request, RankToolsForScenario $search): JsonResponse
    {
        $outcome = $search->execute($request->authenticatedUser(), $request->scenario(), $request->category());

        return ApiResponse::success([
            'scenario_search' => [
                'results' => ScenarioSearchResultResource::collection($outcome->tools)->resolve($request),
                'ai_ranked' => $outcome->aiRanked,
                'cached' => $outcome->cached,
                'provider' => $outcome->provider,
                'model' => $outcome->model,
                'disclaimer' => self::DISCLAIMER,
            ],
        ]);
    }
}
