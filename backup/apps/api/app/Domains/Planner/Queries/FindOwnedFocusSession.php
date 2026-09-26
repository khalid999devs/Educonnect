<?php

declare(strict_types=1);

namespace App\Domains\Planner\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindOwnedFocusSession
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): FocusSession
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = FocusSession::query()
                ->where('user_id', $user->getKey())
                ->where('public_id', $publicId)
                ->with(['task', 'course']);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $session = $query->first();

            if (! $session instanceof FocusSession) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $session);

            return $session;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw PlannerPersistenceFailure::fromQueryException($exception, 'focus.read');
        }
    }
}
