<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Intake\Contracts\HostResolver;

final class FakeHostResolver implements HostResolver
{
    /** @param array<string, list<string>> $map */
    public function __construct(
        private readonly array $map = [],
        private readonly ?string $default = '93.184.216.34',
    ) {}

    /** @return list<string> */
    public function resolve(string $host): array
    {
        if (array_key_exists($host, $this->map)) {
            return $this->map[$host];
        }

        return $this->default === null ? [] : [$this->default];
    }
}
