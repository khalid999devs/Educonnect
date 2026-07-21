<?php

declare(strict_types=1);

namespace App\Domains\Users\Data;

use Carbon\CarbonInterface;

final readonly class SessionSummary
{
    public function __construct(
        public string $id,
        public ?string $ipAddress,
        public ?string $userAgent,
        public CarbonInterface $lastActivity,
        public bool $isCurrent,
    ) {}
}
