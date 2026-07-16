<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\DeleteResearchTopicAction;
use App\Http\Requests\Api\V1\SecondBrain\BrainVersionedMutationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class DeleteResearchTopicController
{
    public function __invoke(
        BrainVersionedMutationRequest $request,
        string $topic,
        DeleteResearchTopicAction $delete,
    ): Response {
        $delete->execute($request->authenticatedUser(), $topic, $request->expectedVersion());

        return ApiResponse::noContent();
    }
}
