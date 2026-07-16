<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\DetachResearchSourceAction;
use App\Http\Requests\Api\V1\SecondBrain\EmptyBrainRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class DetachResearchSourceController
{
    public function __invoke(
        EmptyBrainRequest $request,
        string $topic,
        string $item,
        DetachResearchSourceAction $detach,
    ): Response {
        $detach->execute($request->authenticatedUser(), $topic, $item);

        return ApiResponse::noContent();
    }
}
