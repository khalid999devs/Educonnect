<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Domains\Users\Models\User;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class DashboardRateLimitConfigurationTest extends TestCase
{
    public function test_dashboard_limiter_has_independent_hashed_actor_and_ip_layers(): void
    {
        $limits = $this->limitsFor('dashboard.read', $this->request(actorId: 101, ip: '203.0.113.10'));

        $this->assertCount(2, $limits);
        $this->assertSame(60, $limits[0]->maxAttempts);
        $this->assertSame(60, $limits[1]->maxAttempts);
        $this->assertSame(60, $limits[0]->decaySeconds);
        $this->assertSame(60, $limits[1]->decaySeconds);
        $this->assertNotSame($limits[0]->key, $limits[1]->key);
        $this->assertStringNotContainsString('101', (string) $limits[0]->key);
        $this->assertStringNotContainsString('203.0.113.10', (string) $limits[1]->key);
    }

    public function test_dashboard_limiter_uses_an_independent_bucket(): void
    {
        $request = $this->request(101, '203.0.113.10');
        $dashboard = $this->limitsFor('dashboard.read', $request);
        $brain = $this->limitsFor('brain.read', $request);
        $planner = $this->limitsFor('planner.read', $request);

        $this->assertNotSame($dashboard[0]->key, $brain[0]->key);
        $this->assertNotSame($dashboard[0]->key, $planner[0]->key);
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
            uri: '/api/v1/dashboard',
            method: 'GET',
            server: ['REMOTE_ADDR' => $ip],
        );
        $request->setUserResolver(static fn (): User => $user);

        return $request;
    }
}
