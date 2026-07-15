<?php

declare(strict_types=1);

namespace App\Domains\Resources\Data;

final readonly class StoragePolicyReport
{
    /** @param list<StoragePolicyCheck> $checks */
    public function __construct(public array $checks) {}

    public function passes(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->status !== StoragePolicyCheck::PASS) {
                return false;
            }
        }

        return true;
    }
}
