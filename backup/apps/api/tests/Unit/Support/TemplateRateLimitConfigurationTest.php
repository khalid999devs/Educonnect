<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Domains\Users\Models\User;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TemplateRateLimitConfigurationTest extends TestCase
{
    #[DataProvider('limiterProvider')]
    public function test_template_limiters_have_independent_hashed_actor_and_ip_layers(
        string $name,
        int $attempts,
    ): void {
        $limits = $this->limitsFor($name, $this->request(actorId: 101, ip: '203.0.113.10'));

        $this->assertCount(2, $limits);
        $this->assertSame($attempts, $limits[0]->maxAttempts);
        $this->assertSame($attempts, $limits[1]->maxAttempts);
        $this->assertSame(60, $limits[0]->decaySeconds);
        $this->assertSame(60, $limits[1]->decaySeconds);
        $this->assertNotSame($limits[0]->key, $limits[1]->key);
        $this->assertStringNotContainsString('101', (string) $limits[0]->key);
        $this->assertStringNotContainsString('203.0.113.10', (string) $limits[1]->key);
    }

    public function test_template_limiters_use_independent_buckets(): void
    {
        $request = $this->request(101, '203.0.113.10');
        $templates = $this->limitsFor('templates.read', $request);
        $preferences = $this->limitsFor('templates.preference', $request);
        $copyReads = $this->limitsFor('template-copies.read', $request);
        $copyWrites = $this->limitsFor('template-copies.write', $request);
        $prompts = $this->limitsFor('prompts.read', $request);

        $this->assertNotSame($templates[0]->key, $preferences[0]->key);
        $this->assertNotSame($templates[0]->key, $copyReads[0]->key);
        $this->assertNotSame($copyReads[0]->key, $copyWrites[0]->key);
        $this->assertNotSame($templates[0]->key, $prompts[0]->key);
    }

    /** @return iterable<string, array{string, int}> */
    public static function limiterProvider(): iterable
    {
        yield 'template read' => ['templates.read', 120];
        yield 'template preference' => ['templates.preference', 60];
        yield 'template copy read' => ['template-copies.read', 120];
        yield 'template copy write' => ['template-copies.write', 60];
    }

    /** @return array{Limit, Limit} */
    private function limitsFor(string $name, Request $request): array
    {
        $limiter = RateLimiter::limiter($name);

        $this->assertInstanceOf(Closure::class, $limiter);
        $limits = $limiter($request);
        $this->assertIsArray($limits);
        $this->assertContainsOnlyInstancesOf(Limit::class, $limits);

        return array_values($limits);
    }

    private function request(int $actorId, string $ip): Request
    {
        $user = new User;
        $user->forceFill(['id' => $actorId]);
        $request = Request::create(
            uri: '/api/v1/templates',
            method: 'GET',
            server: ['REMOTE_ADDR' => $ip],
        );
        $request->setUserResolver(static fn (): User => $user);

        return $request;
    }
}
