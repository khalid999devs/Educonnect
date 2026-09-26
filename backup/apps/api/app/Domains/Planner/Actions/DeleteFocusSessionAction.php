<?php

declare(strict_types=1);

namespace App\Domains\Planner\Actions;

use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Exceptions\PlannerVersionConflict;
use App\Domains\Planner\Queries\FindOwnedFocusSession;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteFocusSessionAction
{
    public function __construct(private FindOwnedFocusSession $sessions) {}

    public function execute(User $user, string $publicId, int $expectedVersion): void
    {
        try {
            DB::transaction(function () use ($user, $publicId, $expectedVersion): void {
                $session = $this->sessions->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('delete', $session);

                if ($session->version !== $expectedVersion) {
                    throw new PlannerVersionConflict;
                }

                $session->delete();
            }, 3);
        } catch (QueryException $exception) {
            throw PlannerPersistenceFailure::fromQueryException($exception, 'focus.delete');
        }
    }
}
