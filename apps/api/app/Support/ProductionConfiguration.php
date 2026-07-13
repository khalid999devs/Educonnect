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

        if (! $this->hasValidKey($key, $cipher)) {
            $violations[] = 'APP_KEY must be a valid encryption key';
        }

        if ($this->config->get('app.debug') !== false) {
            $violations[] = 'APP_DEBUG must be false';
        }

        if (! $this->hasValidHttpsUrl($url)) {
            $violations[] = 'APP_URL must use HTTPS';
        }

        if ($this->config->get('database.default') !== 'pgsql') {
            $violations[] = 'DB_CONNECTION must be pgsql';
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

    private function hasValidHttpsUrl(mixed $url): bool
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && is_string(parse_url($url, PHP_URL_HOST))
            && parse_url($url, PHP_URL_HOST) !== '';
    }
}
