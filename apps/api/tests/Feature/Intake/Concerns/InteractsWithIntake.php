<?php

declare(strict_types=1);

namespace Tests\Feature\Intake\Concerns;

use App\Domains\Intake\Contracts\HostResolver;
use Tests\Support\FakeHostResolver;

trait InteractsWithIntake
{
    /** @param array<string, list<string>> $hostMap */
    private function fakeDns(array $hostMap = [], ?string $default = '93.184.216.34'): void
    {
        $this->app->instance(HostResolver::class, new FakeHostResolver($hostMap, $default));
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'intake-test-token',
        ];
    }

    private function configureBrowserBoundary(): void
    {
        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }
}
