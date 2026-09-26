<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Resources\Actions\ReconcileResourceStorageAction;
use Illuminate\Console\Command;

final class ReconcileResourceStorageCommand extends Command
{
    protected $signature = 'resources:reconcile-storage {--limit=100 : Maximum due records to inspect}';

    protected $description = 'Reconcile due private resource staging objects and deletion tombstones.';

    public function handle(ReconcileResourceStorageAction $reconcile): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 500],
        ]);

        if (! is_int($limit)) {
            $this->components->error('The --limit option must be an integer between 1 and 500.');

            return self::INVALID;
        }

        $result = $reconcile->execute($limit);
        $this->components->info(
            "Resource storage reconciliation examined {$result->examined}, cleaned {$result->cleaned}, and deferred {$result->failed}.",
        );

        return $result->failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
