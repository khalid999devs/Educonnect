<?php

declare(strict_types=1);

namespace App\Domains\Intake\Actions;

use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Exceptions\IntakePersistenceFailure;
use App\Domains\Intake\Exceptions\IntakeStateConflict;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Queries\FindOwnedIntakeItem;
use App\Domains\Intake\Support\IntakeEventRecorder;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class CancelIntakeAction
{
    public function __construct(
        private FindOwnedIntakeItem $items,
        private IntakeEventRecorder $events,
    ) {}

    public function execute(User $user, string $publicId): IntakeItem
    {
        try {
            return DB::transaction(function () use ($user, $publicId): IntakeItem {
                $item = $this->items->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $item);

                if ($item->state === IntakeState::Cancelled) {
                    return $item;
                }

                if (! $item->state->isCancellable()) {
                    throw new IntakeStateConflict;
                }

                $from = $item->state;
                $item->forceFill([
                    'state' => IntakeState::Cancelled->value,
                    'failure_code' => null,
                    'cancelled_at' => now(),
                ])->save();
                $this->events->record($item, 'cancelled', $from, IntakeState::Cancelled);

                return $item->refresh()->load(['resource', 'artifacts', 'events']);
            }, 3);
        } catch (QueryException $exception) {
            throw IntakePersistenceFailure::fromQueryException($exception, 'intake.cancel');
        }
    }
}
