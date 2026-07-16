<?php

declare(strict_types=1);

namespace App\Domains\Intake\Actions;

use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Exceptions\IntakePersistenceFailure;
use App\Domains\Intake\Exceptions\IntakeStateConflict;
use App\Domains\Intake\Jobs\ProcessIntakeItem;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Queries\FindOwnedIntakeItem;
use App\Domains\Intake\Support\IntakeEventRecorder;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class RetryIntakeAction
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

                if ($item->state !== IntakeState::FailedRetryable
                    || $item->attempts >= (int) config('intake.max_attempts')) {
                    throw new IntakeStateConflict;
                }

                $item->forceFill([
                    'state' => IntakeState::Queued->value,
                    'failure_code' => null,
                    'queued_at' => now(),
                ])->save();
                $this->events->record($item, 'retried', IntakeState::FailedRetryable, IntakeState::Queued);

                ProcessIntakeItem::dispatch((int) $item->getKey())->afterCommit();

                return $item->refresh()->load(['resource', 'artifacts', 'events']);
            }, 3);
        } catch (QueryException $exception) {
            throw IntakePersistenceFailure::fromQueryException($exception, 'intake.retry');
        }
    }
}
