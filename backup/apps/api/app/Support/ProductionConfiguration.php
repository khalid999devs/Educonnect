<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Encryption\Encrypter;
use RuntimeException;

final readonly class ProductionConfiguration
{
    public function __construct(private Repository $config) {}

    public function assertValid(): void
    {
        $violations = [];
        $key = $this->config->get('app.key');
        $cipher = $this->config->get('app.cipher');
        $url = $this->config->get('app.url');
        $origin = $this->httpsOrigin($url);
        $frontendOrigin = $this->httpsOrigin($this->config->get('app.frontend_url'));
        $adminOrigin = $this->httpsOrigin($this->config->get('app.admin_url'));

        if (! $this->hasValidKey($key, $cipher)) {
            $violations[] = 'APP_KEY must be a valid encryption key';
        }

        if ($this->config->get('app.debug') !== false) {
            $violations[] = 'APP_DEBUG must be false';
        }

        if ($origin === null) {
            $violations[] = 'APP_URL must be an HTTPS origin without a path, query, or fragment';
        }

        if ($frontendOrigin === null) {
            $violations[] = 'FRONTEND_URL must be an HTTPS origin without a path, query, or fragment';
        } elseif ($origin !== null && $frontendOrigin !== $origin) {
            $violations[] = 'APP_URL and FRONTEND_URL must use the same public origin';
        }

        if ($adminOrigin === null) {
            $violations[] = 'ADMIN_URL must be an HTTPS origin without a path, query, or fragment';
        } elseif ($frontendOrigin !== null
            && $this->originHost($adminOrigin) === $this->originHost($frontendOrigin)) {
            $violations[] = 'ADMIN_URL must use a distinct administration host';
        }

        if ($this->config->get('database.default') !== 'pgsql') {
            $violations[] = 'DB_CONNECTION must be pgsql';
        }

        if (! $this->isPositiveBoundedInteger($this->config->get('auth.passwords.users.expire'), 1440)) {
            $violations[] = 'AUTH_PASSWORD_RESET_EXPIRE must be between 1 and 1440 minutes';
        }

        if (! $this->isPositiveBoundedInteger($this->config->get('auth.passwords.users.throttle'), 3600)) {
            $violations[] = 'AUTH_PASSWORD_RESET_THROTTLE must be between 1 and 3600 seconds';
        }

        if (! $this->isPositiveBoundedInteger($this->config->get('auth.verification.expire'), 1440)) {
            $violations[] = 'AUTH_VERIFICATION_EXPIRE must be between 1 and 1440 minutes';
        }

        if ($frontendOrigin !== null && $adminOrigin !== null && $adminOrigin !== $frontendOrigin) {
            if (! $this->containsExactly(
                $this->config->get('cors.allowed_origins'),
                [$frontendOrigin, $adminOrigin],
            )
                || $this->config->get('cors.allowed_origins_patterns') !== []
                || $this->config->get('cors.supports_credentials') !== true) {
                $violations[] = 'CORS_ALLOWED_ORIGINS must contain only the exact FRONTEND_URL and ADMIN_URL origins with credentials enabled';
            }

            if (! $this->containsExactly(
                $this->config->get('sanctum.stateful'),
                [$this->statefulDomain($frontendOrigin), $this->statefulDomain($adminOrigin)],
            )) {
                $violations[] = 'SANCTUM_STATEFUL_DOMAINS must contain only the FRONTEND_URL and ADMIN_URL hosts';
            }
        }

        if ($this->config->get('auth.defaults.guard') !== 'web'
            || $this->config->get('auth.guards.web') !== ['driver' => 'session', 'provider' => 'users']
            || $this->config->get('auth.guards.admin') !== ['driver' => 'session', 'provider' => 'users']
            || $this->config->get('sanctum.guard') !== ['web']) {
            $violations[] = 'web and admin must use isolated session guards while Sanctum authenticates only the web guard';
        }

        if ($this->config->get('session.driver') !== 'database') {
            $violations[] = 'SESSION_DRIVER must be database';
        }

        if (! in_array($this->config->get('session.connection'), [null, 'pgsql'], true)
            || $this->config->get('session.table') !== 'sessions') {
            $violations[] = 'database sessions must use the PostgreSQL sessions table';
        }

        if ($this->config->get('session.domain') !== null) {
            $violations[] = 'SESSION_DOMAIN must be null for a host-only cookie';
        }

        if ($this->config->get('session.cookie') !== '__Host-educonnect-session') {
            $violations[] = 'SESSION_COOKIE must be __Host-educonnect-session';
        }

        if ($this->config->get('session.secure') !== true) {
            $violations[] = 'SESSION_SECURE_COOKIE must be true';
        }

        if ($this->config->get('session.http_only') !== true) {
            $violations[] = 'SESSION_HTTP_ONLY must be true';
        }

        if ($this->config->get('session.same_site') !== 'lax') {
            $violations[] = 'SESSION_SAME_SITE must be lax';
        }

        if ($this->config->get('session.path') !== '/') {
            $violations[] = 'SESSION_PATH must be /';
        }

        if ($this->config->get('session.partitioned') !== false) {
            $violations[] = 'SESSION_PARTITIONED_COOKIE must be false';
        }

        if ($this->config->get('session.encrypt') !== true) {
            $violations[] = 'SESSION_ENCRYPT must be true';
        }

        if ($this->config->get('mail.default') !== 'smtp'
            || $this->config->get('mail.mailers.smtp.transport') !== 'smtp') {
            $violations[] = 'MAIL_MAILER must use the smtp transport';
        }

        if (! is_int($this->config->get('mail.mailers.smtp.timeout'))
            || $this->config->get('mail.mailers.smtp.timeout') < 1) {
            $violations[] = 'MAIL_TIMEOUT must be a positive number of seconds';
        }

        if (! is_string($this->config->get('mail.from.address'))
            || filter_var($this->config->get('mail.from.address'), FILTER_VALIDATE_EMAIL) === false) {
            $violations[] = 'MAIL_FROM_ADDRESS must be a valid email address';
        }

        if (! $this->usesPersistentDriver('queue.default', 'queue.connections', ['database', 'redis'])) {
            $violations[] = 'QUEUE_CONNECTION must be database or redis';
        } elseif ($this->config->get('queue.connections.'.$this->config->get('queue.default').'.after_commit') !== true) {
            $violations[] = 'QUEUE_AFTER_COMMIT must be true';
        }

        if (! $this->usesPersistentDriver('cache.default', 'cache.stores', ['database', 'redis'])) {
            $violations[] = 'CACHE_STORE must be database or redis';
        }

        $resourceDisk = $this->config->get('resources.disk');

        if (! is_string($resourceDisk)
            || $resourceDisk === ''
            || $this->config->get("filesystems.disks.{$resourceDisk}.driver") !== 's3') {
            $violations[] = 'RESOURCE_STORAGE_DISK must select a configured S3 disk';
        } elseif ($this->config->get("filesystems.disks.{$resourceDisk}.visibility") !== 'private'
            || $this->config->get("filesystems.disks.{$resourceDisk}.serve") !== false
            || $this->config->get("filesystems.disks.{$resourceDisk}.throw") !== true) {
            $violations[] = 'resource object storage must be private, non-serving, and exception-enabled';
        } else {
            $bucket = $this->config->get("filesystems.disks.{$resourceDisk}.bucket");
            $region = $this->config->get("filesystems.disks.{$resourceDisk}.region");
            $root = $this->config->get("filesystems.disks.{$resourceDisk}.root");
            $endpoint = $this->config->get("filesystems.disks.{$resourceDisk}.endpoint");
            $http = $this->config->get("filesystems.disks.{$resourceDisk}.http");

            if (! $this->isNonBlankString($bucket) || ! $this->isNonBlankString($region)) {
                $violations[] = 'resource object storage requires a bucket and region';
            }

            if (! $this->isSafeObjectPrefix($root) || $root === 'educonnect/local') {
                $violations[] = 'AWS_ROOT must be an explicit non-local environment-specific object prefix';
            }

            if ($endpoint !== null && $endpoint !== '' && $this->httpsOrigin($endpoint) === null) {
                $violations[] = 'AWS_ENDPOINT must be an HTTPS origin when configured';
            }

            $connectTimeout = is_array($http) ? ($http['connect_timeout'] ?? null) : null;
            $requestTimeout = is_array($http) ? ($http['timeout'] ?? null) : null;

            if (! $this->isFiniteNumberBetween($connectTimeout, 1, 10)) {
                $violations[] = 'AWS_CONNECT_TIMEOUT must be between 1 and 10 seconds';
            }

            if (! $this->isFiniteNumberBetween($requestTimeout, 60, 120)) {
                $violations[] = 'AWS_REQUEST_TIMEOUT must be between 60 and 120 seconds';
            }
        }

        if ($this->config->get('resources.max_upload_bytes') !== 25 * 1024 * 1024
            || $this->config->get('resources.upload_ttl_seconds') !== 600
            || $this->config->get('resources.download_ttl_seconds') !== 300
            || $this->config->get('resources.cleanup_grace_seconds') !== 60
            || $this->config->get('resources.late_upload_reap_seconds') !== 86_400
            || $this->config->get('resources.staging_lifecycle_max_days') !== 1) {
            $violations[] = 'resource upload limits and signed-access lifetimes must match the reviewed policy';
        }

        if ($violations !== []) {
            throw new RuntimeException('Invalid production configuration: '.implode('; ', $violations).'.');
        }
    }

    private function hasValidKey(mixed $key, mixed $cipher): bool
    {
        if (! is_string($key) || ! is_string($cipher) || trim($key) === '') {
            return false;
        }

        $decodedKey = str_starts_with($key, 'base64:')
            ? base64_decode(substr($key, 7), true)
            : $key;

        return is_string($decodedKey) && Encrypter::supported($decodedKey, $cipher);
    }

    private function httpsOrigin(mixed $url): ?string
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
            return null;
        }

        $origin = 'https://'.strtolower($parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    private function statefulDomain(string $origin): string
    {
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);

        return (string) $host.($port === null ? '' : ':'.$port);
    }

    private function originHost(string $origin): string
    {
        return strtolower((string) parse_url($origin, PHP_URL_HOST));
    }

    private function isPositiveBoundedInteger(mixed $value, int $maximum): bool
    {
        return is_int($value) && $value >= 1 && $value <= $maximum;
    }

    private function isNonBlankString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function isFiniteNumberBetween(mixed $value, float $minimum, float $maximum): bool
    {
        return (is_int($value) || is_float($value))
            && is_finite((float) $value)
            && $value >= $minimum
            && $value <= $maximum;
    }

    private function isSafeObjectPrefix(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^[a-z0-9][a-z0-9_\/-]*$/', $value) === 1
            && ! str_contains($value, '//')
            && ! str_contains($value, '..')
            && ! str_ends_with($value, '/');
    }

    /**
     * @param  list<string>  $expected
     */
    private function containsExactly(mixed $actual, array $expected): bool
    {
        if (! is_array($actual)
            || count($actual) !== count($expected)
            || array_filter($actual, is_string(...)) !== $actual) {
            return false;
        }

        $actual = array_values($actual);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);

        return $actual === $expected;
    }

    /**
     * @param  list<string>  $supportedDrivers
     */
    private function usesPersistentDriver(
        string $defaultKey,
        string $connectionsKey,
        array $supportedDrivers,
    ): bool {
        $default = $this->config->get($defaultKey);

        return is_string($default)
            && in_array($default, $supportedDrivers, true)
            && $this->config->get("{$connectionsKey}.{$default}.driver") === $default;
    }
}
