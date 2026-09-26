<?php

declare(strict_types=1);

namespace App\Domains\Resources\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Resources\Exceptions\ResourcePersistenceFailure;
use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindOwnedResource
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): Resource
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = Resource::query()
                ->where('user_id', $user->getKey())
                ->where('public_id', $publicId)
                ->with(['course', 'storedFile']);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $resource = $query->first();

            if (! $resource instanceof Resource) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $resource);

            return $resource;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.read');
        }
    }
}
