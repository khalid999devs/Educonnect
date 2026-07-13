<?php

namespace App\Providers;

use App\Support\ProductionConfiguration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(ProductionConfiguration $productionConfiguration): void
    {
        if ($this->app->environment('production') && ! $this->isPackageDiscovery()) {
            $productionConfiguration->assertValid();
        }

        RateLimiter::for('auth', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));
            $key = hash('sha256', $email.'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });
    }

    private function isPackageDiscovery(): bool
    {
        if (! $this->app->runningInConsole()) {
            return false;
        }

        $arguments = $_SERVER['argv'] ?? [];

        return is_array($arguments) && ($arguments[1] ?? null) === 'package:discover';
    }
}
