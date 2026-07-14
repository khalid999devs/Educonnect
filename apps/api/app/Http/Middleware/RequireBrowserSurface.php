<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RequireBrowserSurface
{
    public const ADMIN = 'admin';

    public const STUDENT = 'student';

    public function handle(Request $request, Closure $next, string $surface): Response
    {
        $configuredUrl = match ($surface) {
            self::ADMIN => config('app.admin_url'),
            self::STUDENT => config('app.frontend_url'),
            default => null,
        };
        $expectedOrigin = is_string($configuredUrl)
            ? $this->origin($configuredUrl)
            : null;
        $expectedHost = is_string($configuredUrl)
            ? $this->hostWithOptionalPort($configuredUrl)
            : null;
        $sourceHeaders = array_values(array_filter([
            $request->headers->get('origin'),
            $request->headers->get('referer'),
        ], static fn (?string $value): bool => $value !== null && trim($value) !== ''));

        if ($expectedOrigin === null
            || count($sourceHeaders) === 0
            || array_any(
                $sourceHeaders,
                fn (string $value): bool => ($origin = $this->headerOrigin($value)) === null
                    || ! hash_equals($expectedOrigin, $origin),
            )) {
            throw new NotFoundHttpException;
        }

        if (app()->environment('production')
            && ($expectedHost === null
            || ! hash_equals($expectedHost, strtolower($request->getHttpHost())))) {
            throw new NotFoundHttpException;
        }

        return $next($request);
    }

    private function headerOrigin(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $this->origin(trim($value));
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        $origin = strtolower((string) $parts['scheme']).'://'.strtolower((string) $parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    private function hostWithOptionalPort(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return strtolower($host).(is_int($port) ? ':'.$port : '');
    }
}
