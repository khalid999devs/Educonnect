<?php

declare(strict_types=1);

namespace Tests\Feature\Progress\Concerns;

trait InteractsWithProgress
{
    private function url(string $timezone = 'UTC', ?string $window = null): string
    {
        $url = '/api/v1/progress?timezone='.urlencode($timezone);

        return $window === null ? $url : $url.'&window='.urlencode($window);
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
