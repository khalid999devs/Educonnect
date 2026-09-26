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

final class ResourceRateLimitConfigurationTest extends TestCase
{
    #[DataProvider('limiterProvider')]
    public function test_resource_limiters_have_independent_hashed_actor_and_ip_layers(
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

    public function test_actor_and_ip_keys_change_only_with_their_own_dimension(): void
    {
        $baseline = $this->limitsFor(
            'resources.upload',
            $this->request(actorId: 101, ip: '203.0.113.10'),
        );
        $differentActor = $this->limitsFor(
            'resources.upload',
            $this->request(actorId: 202, ip: '203.0.113.10'),
        );
        $differentIp = $this->limitsFor(
            'resources.upload',
            $this->request(actorId: 101, ip: '203.0.113.11'),
        );

        $this->assertNotSame($baseline[0]->key, $differentActor[0]->key);
        $this->assertSame($baseline[1]->key, $differentActor[1]->key);
        $this->assertSame($baseline[0]->key, $differentIp[0]->key);
        $this->assertNotSame($baseline[1]->key, $differentIp[1]->key);
    }

    public function test_each_resource_limiter_uses_an_independent_bucket(): void
    {
        $request = $this->request(actorId: 101, ip: '203.0.113.10');
        $actorKeys = [];
        $ipKeys = [];

        foreach (array_keys(iterator_to_array(self::limiterProvider())) as $suffix) {
            $limits = $this->limitsFor('resources.'.$suffix, $request);
            $actorKeys[] = $limits[0]->key;
            $ipKeys[] = $limits[1]->key;
        }

        $this->assertCount(5, array_unique($actorKeys));
        $this->assertCount(5, array_unique($ipKeys));
    }

    /** @return iterable<string, array{string, int}> */
    public static function limiterProvider(): iterable
    {
        yield 'read' => ['resources.read', 120];
        yield 'write' => ['resources.write', 60];
        yield 'upload' => ['resources.upload', 20];
        yield 'download' => ['resources.download', 60];
        yield 'destructive' => ['resources.destructive', 20];
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
            uri: '/api/v1/resources',
            method: 'POST',
            server: ['REMOTE_ADDR' => $ip],
        );
        $request->setUserResolver(static fn (): User => $user);

        return $request;
    }
}
