<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ProductionConfiguration;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class ProductionConfigurationTest extends TestCase
{
    public function test_valid_production_configuration_passes(): void
    {
        $this->setValidProductionConfiguration();

        $this->app->make(ProductionConfiguration::class)->assertValid();

        $this->addToAssertionCount(1);
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function test_invalid_production_configuration_fails_clearly(
        string $key,
        mixed $value,
        string $expectedMessage,
    ): void {
        $this->setValidProductionConfiguration();
        Config::set($key, $value);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->app->make(ProductionConfiguration::class)->assertValid();
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'missing application key' => ['app.key', null, 'APP_KEY must be a valid encryption key'];
        yield 'debug enabled' => ['app.debug', true, 'APP_DEBUG must be false'];
        yield 'insecure application URL' => [
            'app.url',
            'http://educonnect.example',
            'APP_URL must be an HTTPS origin without a path, query, or fragment',
        ];
        yield 'application URL with a path' => [
            'app.url',
            'https://educonnect.example/backend',
            'APP_URL must be an HTTPS origin without a path, query, or fragment',
        ];
        yield 'application URL with credentials' => [
            'app.url',
            'https://deploy@educonnect.example',
            'APP_URL must be an HTTPS origin without a path, query, or fragment',
        ];
        yield 'malformed HTTPS application URL' => [
            'app.url',
            'https:educonnect.example',
            'APP_URL must be an HTTPS origin without a path, query, or fragment',
        ];
        yield 'insecure frontend URL' => [
            'app.frontend_url',
            'http://educonnect.example',
            'FRONTEND_URL must be an HTTPS origin without a path, query, or fragment',
        ];
        yield 'different frontend origin' => [
            'app.frontend_url',
            'https://web.educonnect.example',
            'APP_URL and FRONTEND_URL must use the same public origin',
        ];
        yield 'wrong database driver' => ['database.default', 'sqlite', 'DB_CONNECTION must be pgsql'];
        yield 'non-expiring password reset' => [
            'auth.passwords.users.expire',
            0,
            'AUTH_PASSWORD_RESET_EXPIRE must be between 1 and 1440 minutes',
        ];
        yield 'unthrottled password reset email' => [
            'auth.passwords.users.throttle',
            0,
            'AUTH_PASSWORD_RESET_THROTTLE must be between 1 and 3600 seconds',
        ];
        yield 'non-expiring verification link' => [
            'auth.verification.expire',
            0,
            'AUTH_VERIFICATION_EXPIRE must be between 1 and 1440 minutes',
        ];
        yield 'unexpected CORS origin' => [
            'cors.allowed_origins',
            ['https://admin.educonnect.example'],
            'CORS_ALLOWED_ORIGINS must contain only the exact FRONTEND_URL origin with credentials enabled',
        ];
        yield 'CORS origin pattern' => [
            'cors.allowed_origins_patterns',
            ['#^https://.*\\.educonnect\\.example$#'],
            'CORS_ALLOWED_ORIGINS must contain only the exact FRONTEND_URL origin with credentials enabled',
        ];
        yield 'credentials disabled' => [
            'cors.supports_credentials',
            false,
            'CORS_ALLOWED_ORIGINS must contain only the exact FRONTEND_URL origin with credentials enabled',
        ];
        yield 'unexpected Sanctum stateful domain' => [
            'sanctum.stateful',
            ['api.educonnect.example'],
            'SANCTUM_STATEFUL_DOMAINS must contain only the FRONTEND_URL host',
        ];
        yield 'non-database sessions' => ['session.driver', 'redis', 'SESSION_DRIVER must be database'];
        yield 'wrong session connection' => [
            'session.connection',
            'legacy',
            'database sessions must use the PostgreSQL sessions table',
        ];
        yield 'wrong session table' => [
            'session.table',
            'legacy_sessions',
            'database sessions must use the PostgreSQL sessions table',
        ];
        yield 'shared-domain session cookie' => [
            'session.domain',
            '.educonnect.example',
            'SESSION_DOMAIN must be null for a host-only cookie',
        ];
        yield 'unprefixed session cookie' => [
            'session.cookie',
            'educonnect-session',
            'SESSION_COOKIE must be __Host-educonnect-session',
        ];
        yield 'insecure session cookie' => ['session.secure', false, 'SESSION_SECURE_COOKIE must be true'];
        yield 'script-readable session cookie' => ['session.http_only', false, 'SESSION_HTTP_ONLY must be true'];
        yield 'cross-site session cookie' => ['session.same_site', 'none', 'SESSION_SAME_SITE must be lax'];
        yield 'narrow session path' => ['session.path', '/api', 'SESSION_PATH must be /'];
        yield 'partitioned session cookie' => [
            'session.partitioned',
            true,
            'SESSION_PARTITIONED_COOKIE must be false',
        ];
        yield 'unencrypted session payload' => ['session.encrypt', false, 'SESSION_ENCRYPT must be true'];
        yield 'non-delivery mailer' => ['mail.default', 'log', 'MAIL_MAILER must use the smtp transport'];
        yield 'unbounded SMTP timeout' => [
            'mail.mailers.smtp.timeout',
            0,
            'MAIL_TIMEOUT must be a positive number of seconds',
        ];
        yield 'invalid sender address' => [
            'mail.from.address',
            'not-an-email',
            'MAIL_FROM_ADDRESS must be a valid email address',
        ];
        yield 'synchronous queue' => ['queue.default', 'sync', 'QUEUE_CONNECTION must be database or redis'];
        yield 'queue before commit' => [
            'queue.connections.database.after_commit',
            false,
            'QUEUE_AFTER_COMMIT must be true',
        ];
        yield 'in-memory cache' => ['cache.default', 'array', 'CACHE_STORE must be database or redis'];
    }

    private function setValidProductionConfiguration(): void
    {
        Config::set([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.cipher' => 'AES-256-CBC',
            'app.debug' => false,
            'app.url' => 'https://educonnect.example',
            'app.frontend_url' => 'https://educonnect.example',
            'database.default' => 'pgsql',
            'auth.passwords.users.expire' => 60,
            'auth.passwords.users.throttle' => 60,
            'auth.verification.expire' => 60,
            'cors.allowed_origins' => ['https://educonnect.example'],
            'cors.allowed_origins_patterns' => [],
            'cors.supports_credentials' => true,
            'sanctum.stateful' => ['educonnect.example'],
            'session.driver' => 'database',
            'session.connection' => null,
            'session.table' => 'sessions',
            'session.domain' => null,
            'session.cookie' => '__Host-educonnect-session',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.path' => '/',
            'session.partitioned' => false,
            'session.encrypt' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.timeout' => 10,
            'mail.from.address' => 'hello@educonnect.example',
            'queue.default' => 'database',
            'queue.connections.database.driver' => 'database',
            'queue.connections.database.after_commit' => true,
            'cache.default' => 'database',
            'cache.stores.database.driver' => 'database',
        ]);
    }
}
