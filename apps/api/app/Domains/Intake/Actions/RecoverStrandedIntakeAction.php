<?php

declare(strict_types=1);

namespace App\Domains\Intake\Actions;

use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Jobs\ProcessIntakeItem;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Support\IntakeEventRecorder;
use App\Domains\Telemetry\Enums\TelemetryOutcome;
use App\Domains\Telemetry\Support\TelemetryRecorder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Bounded, idempotent recovery for the intake pipeline (Phase 28 reliability).
 *
 * A worker that is hard-killed (OOM, SIGKILL) never runs a job's `failed()`
 * hook, so an item can be stranded in a transient state forever. This action
 * is the backstop: it reaps items stuck past the stranded threshold and
 * auto-requeues retryable failures on an exponential backoff, always within the
 * existing attempt budget so a genuinely broken item still comes to rest.
 */
final readonly class RecoverStrandedIntakeAction
{
    private const TRANSIENT_STATES = ['queued', 'extracting', 'organizing'];

    public function __construct(
        private IntakeEventRecorder $events,
        private TelemetryRecorder $telemetry,
    ) {}

    public function execute(int $limit): IntakeRecoveryResult
    {
        $cutoff = CarbonImmutable::now()->subSeconds(max(60, (int) config('intake.stranded_after_seconds')));

        $reaped = 0;
        $redispatched = 0;

        foreach ($this->strandedIds($cutoff, $limit) as $id) {
            $outcome = $this->recoverStranded((int) $id, $cutoff);

            if ($outcome === 'reaped') {
                $reaped++;
            } elseif ($outcome === 'redispatched') {
                $redispatched++;
            }
        }

        $requeued = $this->requeueBackedOff($limit);

        return new IntakeRecoveryResult($reaped, $redispatched, $requeued);
    }

    /** @return list<int> */
    private function strandedIds(CarbonInterface $cutoff, int $limit): array
    {
        return IntakeItem::query()
            ->whereIn('state', self::TRANSIENT_STATES)
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->where(function ($queued) use ($cutoff): void {
                        $queued->where('state', IntakeState::Queued->value)
                            ->where(function ($stale) use ($cutoff): void {
                                $stale->whereNull('queued_at')->orWhere('queued_at', '<', $cutoff);
                            });
                    })
                    ->orWhere(function ($processing) use ($cutoff): void {
                        $processing->whereIn('state', [IntakeState::Extracting->value, IntakeState::Organizing->value])
                            ->where(function ($stale) use ($cutoff): void {
                                $stale->whereNull('started_at')->orWhere('started_at', '<', $cutoff);
                            });
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function recoverStranded(int $id, CarbonInterface $cutoff): ?string
    {
        return DB::transaction(function () use ($id, $cutoff): ?string {
            $item = IntakeItem::query()->whereKey($id)->lockForUpdate()->first();

            if (! $item instanceof IntakeItem || ! $this->stillStranded($item, $cutoff)) {
                return null;
            }

            // A validly-queued item whose worker was lost only needs another
            // dispatch (the claim is idempotent); it is not a failure.
            if ($item->state === IntakeState::Queued) {
                $item->forceFill(['queued_at' => now()])->save();
                $this->events->record($item, 'recovered_requeued', null, null, 'stranded queue entry re-dispatched');
                $this->telemetry->recordIntakeJob('intake.recover', TelemetryOutcome::Degraded, null, ['action' => 'redispatch']);
                ProcessIntakeItem::dispatch($id)->afterCommit();

                return 'redispatched';
            }

            // An item stranded mid-extraction/organization had its worker die;
            // fail it (retryable within budget) so it can recover or come to rest.
            $retryable = $item->attempts < (int) config('intake.max_attempts');
            $from = $item->state;
            $item->forceFill([
                'state' => $retryable ? IntakeState::FailedRetryable->value : IntakeState::FailedFinal->value,
                'failure_code' => IntakeFailureCode::StrandedTimeout->value,
                'finished_at' => now(),
            ])->save();
            $this->events->record(
                $item,
                'reaped',
                $from,
                $retryable ? IntakeState::FailedRetryable : IntakeState::FailedFinal,
                'stranded past the recovery threshold',
            );
            $this->telemetry->recordIntakeJob('intake.recover', TelemetryOutcome::Failure, null, ['action' => 'reap']);

            return 'reaped';
        }, 3);
    }

    private function requeueBackedOff(int $limit): int
    {
        if (! (bool) config('intake.auto_retry.enabled', true)) {
            return 0;
        }

        $maxAttempts = (int) config('intake.max_attempts');
        $candidates = IntakeItem::query()
            ->where('state', IntakeState::FailedRetryable->value)
            ->where('attempts', '<', $maxAttempts)
            ->whereNotNull('finished_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'attempts', 'finished_at']);

        $requeued = 0;

        foreach ($candidates as $candidate) {
            $finishedAt = $candidate->getAttribute('finished_at');

            if (! $finishedAt instanceof CarbonInterface) {
                continue;
            }

            $readyAt = $finishedAt->clone()->addSeconds($this->backoffSeconds((int) $candidate->getAttribute('attempts')));

            if ($readyAt->isFuture()) {
                continue;
            }

            if ($this->requeueOne((int) $candidate->getAttribute('id'))) {
                $requeued++;
            }
        }

        return $requeued;
    }

    private function requeueOne(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $item = IntakeItem::query()->whereKey($id)->lockForUpdate()->first();

            if (! $item instanceof IntakeItem
                || $item->state !== IntakeState::FailedRetryable
                || $item->attempts >= (int) config('intake.max_attempts')) {
                return false;
            }

            $item->forceFill([
                'state' => IntakeState::Queued->value,
                'failure_code' => null,
                'queued_at' => now(),
            ])->save();
            $this->events->record($item, 'auto_retried', IntakeState::FailedRetryable, IntakeState::Queued, 'automatic backoff retry');
            $this->telemetry->recordIntakeJob('intake.recover', TelemetryOutcome::Degraded, null, ['action' => 'auto_retry']);
            ProcessIntakeItem::dispatch($id)->afterCommit();

            return true;
        }, 3);
    }

    private function stillStranded(IntakeItem $item, CarbonInterface $cutoff): bool
    {
        if ($item->state === IntakeState::Queued) {
            return $item->queued_at === null || $item->queued_at->lessThan($cutoff);
        }

        if (in_array($item->state, [IntakeState::Extracting, IntakeState::Organizing], true)) {
            return $item->started_at === null || $item->started_at->lessThan($cutoff);
        }

        return false;
    }

    private function backoffSeconds(int $attempts): int
    {
        $base = max(1, (int) config('intake.auto_retry.base_delay_seconds', 60));
        $max = max(1, (int) config('intake.auto_retry.max_delay_seconds', 900));
        $delay = $base * (2 ** max(0, $attempts - 1));

        return (int) min($delay, $max);
    }
}
