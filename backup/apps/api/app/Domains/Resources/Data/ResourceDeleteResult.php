<?php

declare(strict_types=1);

namespace App\Domains\Resources\Data;

use App\Domains\Resources\Models\Resource;

final readonly class ResourceDeleteResult
{
    public function __construct(
        public bool $pending,
        public ?Resource $resource,
    ) {}
}
