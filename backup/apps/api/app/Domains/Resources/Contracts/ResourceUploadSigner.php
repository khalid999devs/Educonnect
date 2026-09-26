<?php

declare(strict_types=1);

namespace App\Domains\Resources\Contracts;

use App\Domains\Resources\Data\UploadPutGrant;
use Carbon\CarbonImmutable;

interface ResourceUploadSigner
{
    public function sign(
        string $key,
        CarbonImmutable $expiresAt,
        string $mimeType,
        int $size,
    ): UploadPutGrant;
}
