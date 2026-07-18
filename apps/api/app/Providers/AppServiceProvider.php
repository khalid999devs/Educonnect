<?php

namespace App\Providers;

use App\Domains\Copilot\Contracts\ChatProvider;
use App\Domains\Copilot\OpenAiChatProvider;
use App\Domains\Intake\AI\OpenAiClassificationProvider;
use App\Domains\Intake\AI\RuleBasedClassificationProvider;
use App\Domains\Intake\Contracts\AIProvider;
use App\Domains\Intake\Contracts\HostResolver;
use App\Domains\Intake\Contracts\IntakeContentExtractor;
use App\Domains\Intake\Support\DnsHostResolver;
use App\Domains\Intake\Support\PlainTextExtractor;
use App\Domains\Resources\Contracts\ResourceUploadSigner;
use App\Domains\Resources\Support\S3StrictPutUploadSigner;
use App\Support\Ai\OpenAiClient;
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
        $this->app->bind(ResourceUploadSigner::class, S3StrictPutUploadSigner::class);
        $this->app->bind(HostResolver::class, DnsHostResolver::class);
        $this->app->bind(IntakeContentExtractor::class, PlainTextExtractor::class);
        /*
         * The OpenAI provider activates only when a key is configured; the
         * ClassificationPolicy chain always keeps the deterministic
         * rule-based fallback behind it.
         */
        $this->app->bind(
            AIProvider::class,
            OpenAiClient::configured()
                ? OpenAiClassificationProvider::class
                : RuleBasedClassificationProvider::class,
        );
        $this->app->bind(ChatProvider::class, OpenAiChatProvider::class);

        $postgresConnection = config('database.connections.pgsql');

        if (! is_array($postgresConnection)) {
            throw new LogicException('PostgreSQL connection configuration is missing.');
        }

        $mailers = $this->supportedConfiguration('mail.mailers', ['smtp', 'log', 'array']);
        $cacheStores = $this->supportedConfiguration('cache.stores', ['array', 'database', 'redis']);
        $queueConnections = $this->supportedConfiguration('queue.connections', ['sync', 'database', 'redis']);
        $filesystemDisks = $this->supportedConfiguration('filesystems.disks', ['local', 's3']);
        $logChannels = $this->supportedConfiguration(
            'logging.channels',
            ['stack', 'single', 'stderr', 'null', 'emergency'],
        );

        // Laravel merges framework templates; retain only explicitly supported drivers.
        config()->set('database.connections', ['pgsql' => $postgresConnection]);
        config()->set('mail.mailers', $mailers);
        config()->set('cache.stores', $cacheStores);
        config()->set('queue.connections', $queueConnections);
        config()->set('filesystems.disks', $filesystemDisks);
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
        $this->registerResourceRateLimiters();
        $this->registerToolRateLimiters();
        $this->registerGuidanceRateLimiters();
        $this->registerTemplateRateLimiters();
        $this->registerIntakeRateLimiters();
        $this->registerSecondBrainRateLimiters();
        $this->registerDashboardRateLimiters();
        $this->registerCopilotRateLimiters();
        $this->registerCommunityRateLimiters();
        $this->registerMentorRateLimiters();
        $this->registerAdminRateLimiters();
    }

    private function registerAdminRateLimiters(): void
    {
        RateLimiter::for('admin.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'admin-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        // Sensitive operational writes (suspend, role change, verify, resolve).
        RateLimiter::for('admin.write', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'admin-write',
            actorAttempts: 40,
            ipAttempts: 40,
        ));
    }

    private function registerCommunityRateLimiters(): void
    {
        RateLimiter::for('community.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'community-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('community.write', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'community-write',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
        // Deliberately tight: reporting is an abuse-prone action.
        RateLimiter::for('community.report', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'community-report',
            actorAttempts: 20,
            ipAttempts: 20,
        ));
        RateLimiter::for('community.destructive', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'community-destructive',
            actorAttempts: 30,
            ipAttempts: 30,
        ));
        RateLimiter::for('moderation.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'moderation-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('moderation.write', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'moderation-write',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
    }

    private function registerMentorRateLimiters(): void
    {
        RateLimiter::for('mentor.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'mentor-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('mentor.write', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'mentor-write',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
        // Deliberately tight: an unsolicited help request reaches another student.
        RateLimiter::for('mentor.request', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'mentor-request',
            actorAttempts: 20,
            ipAttempts: 20,
        ));
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

    private function registerResourceRateLimiters(): void
    {
        RateLimiter::for('resources.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'resources-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('resources.write', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'resources-write',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
        RateLimiter::for('resources.upload', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'resources-upload',
            actorAttempts: 20,
            ipAttempts: 20,
        ));
        RateLimiter::for('resources.download', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'resources-download',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
        RateLimiter::for('resources.destructive', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'resources-destructive',
            actorAttempts: 20,
            ipAttempts: 20,
        ));
    }

    private function registerToolRateLimiters(): void
    {
        RateLimiter::for('tools.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'tools-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('tools.preference', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'tools-preference',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
    }

    private function registerGuidanceRateLimiters(): void
    {
        RateLimiter::for('prompts.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'prompts-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('prompts.preference', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'prompts-preference',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
        RateLimiter::for('workflows.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'workflows-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('workflows.preference', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'workflows-preference',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
        RateLimiter::for('guidance.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'guidance-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
    }

    private function registerTemplateRateLimiters(): void
    {
        RateLimiter::for('templates.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'templates-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('templates.preference', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'templates-preference',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
        RateLimiter::for('template-copies.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'template-copies-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('template-copies.write', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'template-copies-write',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
    }

    private function registerIntakeRateLimiters(): void
    {
        RateLimiter::for('intake.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'intake-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('intake.write', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'intake-write',
            actorAttempts: 30,
            ipAttempts: 30,
        ));
    }

    private function registerDashboardRateLimiters(): void
    {
        RateLimiter::for('dashboard.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'dashboard-read',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
    }

    private function registerCopilotRateLimiters(): void
    {
        RateLimiter::for('copilot.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'copilot-read',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
        // Deliberately tight: every message fans out to a paid AI provider.
        RateLimiter::for('copilot.message', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'copilot-message',
            actorAttempts: 10,
            ipAttempts: 10,
        ));
    }

    private function registerSecondBrainRateLimiters(): void
    {
        RateLimiter::for('brain.read', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'brain-read',
            actorAttempts: 120,
            ipAttempts: 120,
        ));
        RateLimiter::for('brain.write', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'brain-write',
            actorAttempts: 60,
            ipAttempts: 60,
        ));
        RateLimiter::for('brain.destructive', fn (Request $request): array => $this->actorAndIpLimits(
            request: $request,
            scope: 'brain-destructive',
            actorAttempts: 30,
            ipAttempts: 30,
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
