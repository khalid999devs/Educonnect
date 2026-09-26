<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support;

final readonly class FetchedContent
{
    public function __construct(
        public string $finalUrl,
        public string $contentType,
        public string $body,
        public int $byteSize,
    ) {}
}
