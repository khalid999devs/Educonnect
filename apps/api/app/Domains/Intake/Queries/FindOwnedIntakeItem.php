<?php

declare(strict_types=1);

namespace App\Domains\Intake\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Intake\Exceptions\IntakePersistenceFailure;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindOwnedIntakeItem
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): IntakeItem
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = IntakeItem::query()
                ->where('user_id', $user->getKey())
                ->where('public_id', $publicId)
                ->with(['resource', 'artifacts', 'events']);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $item = $query->first();

            if (! $item instanceof IntakeItem) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $item);

            return $item;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw IntakePersistenceFailure::fromQueryException($exception, 'intake.read');
        }
    }
}
