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

final class GuidanceRateLimitConfigurationTest extends TestCase
{
    #[DataProvider('limiterProvider')]
    public function test_guidance_limiters_have_independent_hashed_actor_and_ip_layers(
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

    public function test_prompt_workflow_and_guidance_limiters_use_independent_buckets(): void
    {
        $request = $this->request(101, '203.0.113.10');
        $prompts = $this->limitsFor('prompts.read', $request);
        $workflows = $this->limitsFor('workflows.read', $request);
        $guidance = $this->limitsFor('guidance.read', $request);
        $preference = $this->limitsFor('prompts.preference', $request);

        $this->assertNotSame($prompts[0]->key, $workflows[0]->key);
        $this->assertNotSame($prompts[0]->key, $guidance[0]->key);
        $this->assertNotSame($workflows[0]->key, $guidance[0]->key);
        $this->assertNotSame($prompts[0]->key, $preference[0]->key);
    }

    /** @return iterable<string, array{string, int}> */
    public static function limiterProvider(): iterable
    {
        yield 'prompt read' => ['prompts.read', 120];
        yield 'prompt preference' => ['prompts.preference', 60];
        yield 'workflow read' => ['workflows.read', 120];
        yield 'workflow preference' => ['workflows.preference', 60];
        yield 'guidance read' => ['guidance.read', 120];
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
            uri: '/api/v1/prompts',
            method: 'GET',
            server: ['REMOTE_ADDR' => $ip],
        );
        $request->setUserResolver(static fn (): User => $user);

        return $request;
    }
}
