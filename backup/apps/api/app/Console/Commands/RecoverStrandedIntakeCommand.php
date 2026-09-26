<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Intake\Actions\RecoverStrandedIntakeAction;
use Illuminate\Console\Command;

final class RecoverStrandedIntakeCommand extends Command
{
    protected $signature = 'intake:recover-stranded {--limit=50 : Maximum items to inspect per pass}';

    protected $description = 'Reap stranded intake items and auto-requeue retryable failures with backoff.';

    public function handle(RecoverStrandedIntakeAction $recover): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 500],
        ]);

        if (! is_int($limit)) {
            $this->components->error('The --limit option must be an integer between 1 and 500.');

            return self::INVALID;
        }

        $result = $recover->execute($limit);
        $this->components->info(sprintf(
            'Intake recovery reaped %d, re-dispatched %d, and auto-requeued %d.',
            $result->reaped,
            $result->redispatched,
            $result->requeued,
        ));

        return self::SUCCESS;
    }
}
