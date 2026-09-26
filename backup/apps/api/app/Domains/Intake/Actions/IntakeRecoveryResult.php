<?php

declare(strict_types=1);

namespace App\Domains\Intake\Actions;

final readonly class IntakeRecoveryResult
{
    public function __construct(
        public int $reaped,
        public int $redispatched,
        public int $requeued,
    ) {}
}
