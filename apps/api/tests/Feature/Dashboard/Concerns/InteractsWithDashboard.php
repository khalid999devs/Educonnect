<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard\Concerns;

trait InteractsWithDashboard
{
    private function url(string $timezone = 'UTC'): string
    {
        return '/api/v1/dashboard?timezone='.urlencode($timezone);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
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
