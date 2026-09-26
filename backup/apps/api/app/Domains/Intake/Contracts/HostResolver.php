<?php

declare(strict_types=1);

namespace App\Domains\Intake\Contracts;

interface HostResolver
{
    /**
     * Resolve a hostname to every A/AAAA address, or an empty list when the
     * host does not resolve.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
