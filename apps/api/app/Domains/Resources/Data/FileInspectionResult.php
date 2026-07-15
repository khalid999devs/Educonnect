<?php

declare(strict_types=1);

namespace App\Domains\Resources\Data;

final readonly class FileInspectionResult
{
    public function __construct(
        public string $mimeType,
        public int $size,
        public string $sha256,
    ) {}
}
