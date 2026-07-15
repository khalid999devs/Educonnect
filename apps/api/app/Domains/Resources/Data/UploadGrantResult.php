<?php

declare(strict_types=1);

namespace App\Domains\Resources\Data;

use App\Domains\Resources\Models\Resource;
use Carbon\CarbonImmutable;

final readonly class UploadGrantResult
{
    /** @param array<string, string> $headers */
    public function __construct(
        public Resource $resource,
        public string $url,
        public array $headers,
        public CarbonImmutable $expiresAt,
    ) {}
}
