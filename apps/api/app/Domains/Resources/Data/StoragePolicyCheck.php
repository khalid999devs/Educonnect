<?php

declare(strict_types=1);

namespace App\Domains\Resources\Data;

use InvalidArgumentException;

final readonly class StoragePolicyCheck
{
    public const PASS = 'PASS';

    public const FAIL = 'FAIL';

    public const MANUAL = 'MANUAL';

    public function __construct(
        public string $status,
        public string $message,
    ) {
        if (! in_array($status, [self::PASS, self::FAIL, self::MANUAL], true)) {
            throw new InvalidArgumentException('Unknown storage policy check status.');
        }
    }
}
