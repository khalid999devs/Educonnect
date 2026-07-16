<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\Collection;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindOwnedCollection
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): Collection
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = Collection::query()
                ->where('user_id', $user->getKey())
                ->where('public_id', $publicId);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $collection = $query->first();

            if (! $collection instanceof Collection) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $collection);

            return $collection;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw BrainPersistenceFailure::fromQueryException($exception, 'collection.read');
        }
    }
}
