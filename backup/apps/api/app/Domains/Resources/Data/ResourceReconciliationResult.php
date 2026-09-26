<?php

declare(strict_types=1);

namespace App\Domains\Resources\Data;

final readonly class ResourceReconciliationResult
{
    public function __construct(
        public int $examined,
        public int $cleaned,
        public int $failed,
    ) {}
}
