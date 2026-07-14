<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AuthRateLimitConfigurationTest extends TestCase
{
    #[DataProvider('limiterProvider')]
    public function test_authentication_limiters_have_independent_identity_and_ip_layers(
        string $name,
        int $identityAttempts,
        int $ipAttempts,
    ): void {
        $limits = $this->limitsFor($name, $this->request(' Student@Example.com ', '203.0.113.10'));

        $this->assertCount(2, $limits);
        $this->assertSame($identityAttempts, $limits[0]->maxAttempts);
        $this->assertSame($ipAttempts, $limits[1]->maxAttempts);
        $this->assertSame(60, $limits[0]->decaySeconds);
        $this->assertSame(60, $limits[1]->decaySeconds);
        $this->assertNotSame($limits[0]->key, $limits[1]->key);
        $this->assertStringNotContainsString('student@example.com', (string) $limits[0]->key);
        $this->assertStringNotContainsString('203.0.113.10', (string) $limits[1]->key);
    }

    public function test_email_limit_keys_are_canonical_and_independent_from_ip_keys(): void
    {
        $canonical = $this->limitsFor(
            'auth.login',
            $this->request(' Student@Example.com ', '203.0.113.10'),
        );
        $sameEmailAndIp = $this->limitsFor(
            'auth.login',
            $this->request('student@example.com', '203.0.113.10'),
        );
        $differentEmail = $this->limitsFor(
            'auth.login',
            $this->request('other@example.com', '203.0.113.10'),
        );
        $differentIp = $this->limitsFor(
            'auth.login',
            $this->request('student@example.com', '203.0.113.11'),
        );

        $this->assertSame($canonical[0]->key, $sameEmailAndIp[0]->key);
        $this->assertSame($canonical[1]->key, $sameEmailAndIp[1]->key);
        $this->assertNotSame($canonical[0]->key, $differentEmail[0]->key);
        $this->assertSame($canonical[1]->key, $differentEmail[1]->key);
        $this->assertSame($canonical[0]->key, $differentIp[0]->key);
        $this->assertNotSame($canonical[1]->key, $differentIp[1]->key);
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function limiterProvider(): iterable
    {
        yield 'login' => ['auth.login', 5, 30];
        yield 'registration' => ['auth.register', 3, 10];
        yield 'password email' => ['auth.password-email', 3, 15];
        yield 'password reset' => ['auth.password-reset', 5, 15];
        yield 'verification send' => ['auth.verification-send', 3, 15];
        yield 'verification consume' => ['auth.verification-verify', 10, 30];
        yield 'logout all' => ['auth.logout-all', 5, 30];
    }

    /**
     * @return array{Limit, Limit}
     */
    private function limitsFor(string $name, Request $request): array
    {
        $limiter = RateLimiter::limiter($name);

        $this->assertInstanceOf(Closure::class, $limiter);

        $limits = $limiter($request);

        $this->assertIsArray($limits);
        $this->assertContainsOnlyInstancesOf(Limit::class, $limits);

        return array_values($limits);
    }

    private function request(string $email, string $ip): Request
    {
        return Request::create(
            uri: '/api/v1/auth/test',
            method: 'POST',
            parameters: ['email' => $email],
            server: ['REMOTE_ADDR' => $ip],
        );
    }
}
