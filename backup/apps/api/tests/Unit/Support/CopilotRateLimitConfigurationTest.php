<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Domains\Users\Models\User;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class CopilotRateLimitConfigurationTest extends TestCase
{
    public function test_copilot_message_limiter_is_tight_and_hashed(): void
    {
        $limits = $this->limitsFor('copilot.message', $this->request(101, '203.0.113.10'));

        $this->assertCount(2, $limits);
        $this->assertSame(10, $limits[0]->maxAttempts);
        $this->assertSame(10, $limits[1]->maxAttempts);
        $this->assertNotSame($limits[0]->key, $limits[1]->key);
        $this->assertStringNotContainsString('101', (string) $limits[0]->key);
        $this->assertStringNotContainsString('203.0.113.10', (string) $limits[1]->key);
    }

    public function test_copilot_read_limiter_uses_an_independent_bucket(): void
    {
        $request = $this->request(101, '203.0.113.10');
        $read = $this->limitsFor('copilot.read', $request);
        $message = $this->limitsFor('copilot.message', $request);
        $dashboard = $this->limitsFor('dashboard.read', $request);

        $this->assertSame(60, $read[0]->maxAttempts);
        $this->assertNotSame($read[0]->key, $message[0]->key);
        $this->assertNotSame($read[0]->key, $dashboard[0]->key);
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
            uri: '/api/v1/copilot/messages',
            method: 'POST',
            server: ['REMOTE_ADDR' => $ip],
        );
        $request->setUserResolver(static fn (): User => $user);

        return $request;
    }
}
