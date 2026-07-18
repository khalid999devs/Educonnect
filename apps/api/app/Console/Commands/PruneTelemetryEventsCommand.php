<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Telemetry\Models\TelemetryEvent;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Enforces telemetry retention. Telemetry is diagnostic operational data, not
 * compliance evidence, so it is safely prunable past the configured window.
 */
final class PruneTelemetryEventsCommand extends Command
{
    protected $signature = 'telemetry:prune {--days= : Override the configured retention window}';

    protected $description = 'Delete operational telemetry events older than the retention window.';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3650]])
            : max(1, (int) config('telemetry.retention_days', 14));

        if (! is_int($days)) {
            $this->components->error('The --days option must be an integer between 1 and 3650.');

            return self::INVALID;
        }

        $cutoff = CarbonImmutable::now('UTC')->subDays($days);
        $deleted = TelemetryEvent::query()->where('occurred_at', '<', $cutoff)->delete();

        $this->components->info("Pruned {$deleted} telemetry events older than {$days} days.");

        return self::SUCCESS;
    }
}
