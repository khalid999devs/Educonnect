<?php

declare(strict_types=1);

namespace App\Support\Health;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;

final readonly class ReadinessCheck
{
    public function __construct(
        private Application $application,
        private ConnectionInterface $database,
    ) {}

    public function passes(): bool
    {
        if ($this->application->isDownForMaintenance()) {
            return false;
        }

        $this->database->selectOne('select 1 as ready');

        return true;
    }
}
