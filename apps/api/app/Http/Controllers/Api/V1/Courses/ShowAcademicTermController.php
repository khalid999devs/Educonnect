<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Courses;

use App\Domains\Courses\Queries\FindOwnedAcademicTerm;
use App\Domains\Users\Models\User;
use App\Http\Resources\AcademicTermResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class ShowAcademicTermController
{
    public function __invoke(Request $request, string $term, FindOwnedAcademicTerm $terms): JsonResponse
    {
        $resource = $terms->execute($this->user($request), $term);

        return ApiResponse::success(AcademicTermResource::make($resource)->resolve($request));
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated EduConnect user is required.');
        }

        return $user;
    }
}
