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

        $this->registerAuthenticationRateLimiters();
        $this->registerOnboardingRateLimiters();
        $this->registerAcademicRateLimiters();
        $this->registerPlannerRateLimiters();
    }

    private function registerAuthenticationRateLimiters(): void
    {
        RateLimiter::for('auth.login', fn (Request $request): array => $this->emailAndIpLimits(
            request: $request,
            scope: 'login',
            emailAttempts: 5,
            ipAttempts: 30,
        ));
        RateLimiter::for('auth.admin-login', fn (Request $request): array => $this->emailAndIpLimits(
            request: $request,
            scope: 'admin-login',
            emailAttempts: 5,
            ipAttempts: 15,
        ));
        RateLimiter::for('auth.register', fn (Request $request): array => $this->emailAndIpLimits(
            request: $request,
            scope: 'register',
            emailAttempts: 3,
            ipAttempts: 10,
        ));
        RateLimiter::for('auth.password-email', fn (Request $request): array => $this->emailAndIpLimits(
            request: $request,
            scope: 'password-email',
            emailAttempts: 3,
            ipAttempts: 15,
        ));
        RateLimiter::for('auth.password-reset', fn (Request $request): array => $this->emailAndIpLimits(
            request: $request,
            scope: 'password-reset',
            emailAttempts: 5,
            ipAttempts: 15,
        ));
        RateLimiter::for('auth.verification-send', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'verification-send',
            actorAttempts: 3,
            ipAttempts: 15,
        ));
        RateLimiter::for('auth.verification-verify', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'verification-verify',
            actorAttempts: 10,
            ipAttempts: 30,
        ));
        RateLimiter::for('auth.logout-all', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'logout-all',
            actorAttempts: 5,
            ipAttempts: 30,
        ));
    }

    private function registerOnboardingRateLimiters(): void
    {
        RateLimiter::for('onboarding.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'onboarding-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('onboarding.write', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'onboarding-write',
            actorAttempts: 30,
            ipAttempts: 30,
        ));
        RateLimiter::for('onboarding.complete', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'onboarding-complete',
            actorAttempts: 5,
            ipAttempts: 5,
        ));
    }

    private function registerAcademicRateLimiters(): void
    {
        RateLimiter::for('academic.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'academic-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('academic.write', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'academic-write',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
        RateLimiter::for('academic.destructive', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'academic-destructive',
            actorAttempts: 20,
            ipAttempts: 20,
        ));
    }

    private function registerPlannerRateLimiters(): void
    {
        RateLimiter::for('planner.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'planner-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('planner.write', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'planner-write',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
        RateLimiter::for('planner.destructive', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'planner-destructive',
            actorAttempts: 20,
            ipAttempts: 20,
        ));
    }

    /**
     * @return array{Limit, Limit}
     */
    private function emailAndIpLimits(
        Request $request,
        string $scope,
        int $emailAttempts,
        int $ipAttempts,
    ): array {
        $email = Str::lower(trim((string) $request->input('email')));

        return [
            Limit::perMinute($emailAttempts)->by($this->rateLimitKey($scope, 'email', $email)),
            Limit::perMinute($ipAttempts)->by($this->rateLimitKey($scope, 'ip', $request->ip() ?? 'unknown')),
        ];
    }

    /**
     * @return array{Limit, Limit}
     */
    private function actorAndIpLimits(
        Request $request,
        string $scope,
        int $actorAttempts,
        int $ipAttempts,
    ): array {
        $actor = $request->user()?->getAuthIdentifier();

        if ($actor === null) {
            $routeActor = $request->route('user') ?? $request->route('id');

            if (is_object($routeActor) && method_exists($routeActor, 'getRouteKey')) {
                $routeActor = $routeActor->getRouteKey();
            }

            $actor = is_scalar($routeActor) ? (string) $routeActor : 'guest';
        }

        return [
            Limit::perMinute($actorAttempts)->by($this->rateLimitKey($scope, 'actor', (string) $actor)),
            Limit::perMinute($ipAttempts)->by($this->rateLimitKey($scope, 'ip', $request->ip() ?? 'unknown')),
        ];
    }

    private function rateLimitKey(string $scope, string $dimension, string $value): string
    {
        return $scope.':'.$dimension.':'.hash('sha256', $value);
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
