<?php

declare(strict_types=1);

namespace App\Domains\Resources\Support;

use App\Domains\Resources\Exceptions\InvalidResourceLink;

final class ResourceUrlValidator
{
    public function validate(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if ($url === ''
            || strlen($url) > 2048
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! is_string($parts['host'] ?? null)
            || trim((string) $parts['host']) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || preg_match('/[\x00-\x1F\x7F]/u', $url) === 1) {
            throw new InvalidResourceLink;
        }

        return $url;
    }
}
