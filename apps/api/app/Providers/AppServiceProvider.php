<?php

namespace App\Providers;

use App\Support\ProductionConfiguration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $postgresConnection = config('database.connections.pgsql');

        if (! is_array($postgresConnection)) {
            throw new LogicException('PostgreSQL connection configuration is missing.');
        }

        $mailers = $this->supportedConfiguration('mail.mailers', ['smtp', 'log', 'array']);
        $cacheStores = $this->supportedConfiguration('cache.stores', ['array', 'database', 'redis']);
        $queueConnections = $this->supportedConfiguration('queue.connections', ['sync', 'database', 'redis']);
        $logChannels = $this->supportedConfiguration(
            'logging.channels',
            ['stack', 'single', 'stderr', 'null', 'emergency'],
        );

        // Laravel merges framework templates; retain only explicitly supported drivers.
        config()->set('database.connections', ['pgsql' => $postgresConnection]);
        config()->set('mail.mailers', $mailers);
        config()->set('cache.stores', $cacheStores);
        config()->set('queue.connections', $queueConnections);
        config()->set('logging.channels', $logChannels);
        config()->set('services', []);
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

    /**
     * @param  list<string>  $supportedKeys
     * @return array<string, array<string, mixed>>
     */
    private function supportedConfiguration(string $configurationKey, array $supportedKeys): array
    {
        $configuration = config($configurationKey);

        if (! is_array($configuration)) {
            throw new LogicException("Configuration [{$configurationKey}] is missing.");
        }

        foreach ($supportedKeys as $supportedKey) {
            if (! isset($configuration[$supportedKey]) || ! is_array($configuration[$supportedKey])) {
                throw new LogicException(
                    "Required configuration [{$configurationKey}.{$supportedKey}] is missing.",
                );
            }
        }

        return array_intersect_key($configuration, array_flip($supportedKeys));
    }
}
