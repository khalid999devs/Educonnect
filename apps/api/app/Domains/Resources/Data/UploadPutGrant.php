<?php

declare(strict_types=1);

namespace App\Domains\Resources\Data;

final readonly class UploadPutGrant
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $url,
        public array $headers,
    ) {}
}
