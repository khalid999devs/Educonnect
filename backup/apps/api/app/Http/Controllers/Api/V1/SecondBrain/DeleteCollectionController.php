<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\DeleteCollectionAction;
use App\Http\Requests\Api\V1\SecondBrain\BrainVersionedMutationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class DeleteCollectionController
{
    public function __invoke(
        BrainVersionedMutationRequest $request,
        string $collection,
        DeleteCollectionAction $delete,
    ): Response {
        $delete->execute($request->authenticatedUser(), $collection, $request->expectedVersion());

        return ApiResponse::noContent();
    }
}
