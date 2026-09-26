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

    public function test_standard_aws_s3_without_a_custom_endpoint_passes(): void
    {
        $this->setValidProductionConfiguration();
        Config::set('filesystems.disks.s3.endpoint', '');

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
        yield 'insecure admin URL' => [
            'app.admin_url',
            'http://admin.educonnect.example',
            'ADMIN_URL must be an HTTPS origin without a path, query, or fragment',
        ];
        yield 'shared student and admin origin' => [
            'app.admin_url',
            'https://educonnect.example',
            'ADMIN_URL must use a distinct administration host',
        ];
        yield 'shared student and admin host on different ports' => [
            'app.admin_url',
            'https://educonnect.example:8443',
            'ADMIN_URL must use a distinct administration host',
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
            ['https://educonnect.example', 'https://attacker.example'],
            'CORS_ALLOWED_ORIGINS must contain only the exact FRONTEND_URL and ADMIN_URL origins with credentials enabled',
        ];
        yield 'CORS origin pattern' => [
            'cors.allowed_origins_patterns',
            ['#^https://.*\\.educonnect\\.example$#'],
            'CORS_ALLOWED_ORIGINS must contain only the exact FRONTEND_URL and ADMIN_URL origins with credentials enabled',
        ];
        yield 'credentials disabled' => [
            'cors.supports_credentials',
            false,
            'CORS_ALLOWED_ORIGINS must contain only the exact FRONTEND_URL and ADMIN_URL origins with credentials enabled',
        ];
        yield 'unexpected Sanctum stateful domain' => [
            'sanctum.stateful',
            ['educonnect.example', 'api.educonnect.example'],
            'SANCTUM_STATEFUL_DOMAINS must contain only the FRONTEND_URL and ADMIN_URL hosts',
        ];
        yield 'admin included in Sanctum authentication guards' => [
            'sanctum.guard',
            ['web', 'admin'],
            'web and admin must use isolated session guards while Sanctum authenticates only the web guard',
        ];
        yield 'admin shares the web guard configuration' => [
            'auth.guards.admin',
            ['driver' => 'token', 'provider' => 'users'],
            'web and admin must use isolated session guards while Sanctum authenticates only the web guard',
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
        yield 'local resource storage' => [
            'resources.disk',
            'local',
            'RESOURCE_STORAGE_DISK must select a configured S3 disk',
        ];
        yield 'public resource objects' => [
            'filesystems.disks.s3.visibility',
            'public',
            'resource object storage must be private, non-serving, and exception-enabled',
        ];
        yield 'missing object bucket' => [
            'filesystems.disks.s3.bucket',
            '',
            'resource object storage requires a bucket and region',
        ];
        yield 'unsafe object prefix' => [
            'filesystems.disks.s3.root',
            '../production',
            'AWS_ROOT must be an explicit non-local environment-specific object prefix',
        ];
        yield 'default local object prefix in production' => [
            'filesystems.disks.s3.root',
            'educonnect/local',
            'AWS_ROOT must be an explicit non-local environment-specific object prefix',
        ];
        yield 'insecure object endpoint' => [
            'filesystems.disks.s3.endpoint',
            'http://objects.example',
            'AWS_ENDPOINT must be an HTTPS origin when configured',
        ];
        yield 'unbounded object storage connect timeout' => [
            'filesystems.disks.s3.http.connect_timeout',
            0,
            'AWS_CONNECT_TIMEOUT must be between 1 and 10 seconds',
        ];
        yield 'non-finite object storage connect timeout' => [
            'filesystems.disks.s3.http.connect_timeout',
            INF,
            'AWS_CONNECT_TIMEOUT must be between 1 and 10 seconds',
        ];
        yield 'too-short object storage request timeout' => [
            'filesystems.disks.s3.http.timeout',
            10,
            'AWS_REQUEST_TIMEOUT must be between 60 and 120 seconds',
        ];
        yield 'unbounded object storage request timeout' => [
            'filesystems.disks.s3.http.timeout',
            121,
            'AWS_REQUEST_TIMEOUT must be between 60 and 120 seconds',
        ];
        yield 'unreviewed upload limit' => [
            'resources.max_upload_bytes',
            50 * 1024 * 1024,
            'resource upload limits and signed-access lifetimes must match the reviewed policy',
        ];
    }

    private function setValidProductionConfiguration(): void
    {
        Config::set([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.cipher' => 'AES-256-CBC',
            'app.debug' => false,
            'app.url' => 'https://educonnect.example',
            'app.frontend_url' => 'https://educonnect.example',
            'app.admin_url' => 'https://admin.educonnect.example',
            'database.default' => 'pgsql',
            'auth.passwords.users.expire' => 60,
            'auth.passwords.users.throttle' => 60,
            'auth.verification.expire' => 60,
            'cors.allowed_origins' => [
                'https://educonnect.example',
                'https://admin.educonnect.example',
            ],
            'cors.allowed_origins_patterns' => [],
            'cors.supports_credentials' => true,
            'sanctum.stateful' => ['educonnect.example', 'admin.educonnect.example'],
            'sanctum.guard' => ['web'],
            'auth.defaults.guard' => 'web',
            'auth.guards.web' => ['driver' => 'session', 'provider' => 'users'],
            'auth.guards.admin' => ['driver' => 'session', 'provider' => 'users'],
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
            'resources.disk' => 's3',
            'resources.max_upload_bytes' => 25 * 1024 * 1024,
            'resources.upload_ttl_seconds' => 600,
            'resources.download_ttl_seconds' => 300,
            'resources.cleanup_grace_seconds' => 60,
            'resources.late_upload_reap_seconds' => 86_400,
            'resources.staging_lifecycle_max_days' => 1,
            'filesystems.disks.s3.driver' => 's3',
            'filesystems.disks.s3.visibility' => 'private',
            'filesystems.disks.s3.serve' => false,
            'filesystems.disks.s3.throw' => true,
            'filesystems.disks.s3.bucket' => 'educonnect-production',
            'filesystems.disks.s3.region' => 'auto',
            'filesystems.disks.s3.root' => 'educonnect/production',
            'filesystems.disks.s3.endpoint' => 'https://objects.example',
            'filesystems.disks.s3.http' => [
                'connect_timeout' => 5.0,
                'timeout' => 60.0,
            ],
        ]);
    }
}
