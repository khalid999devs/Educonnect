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

final class AcademicRateLimitConfigurationTest extends TestCase
{
    #[DataProvider('limiterProvider')]
    public function test_academic_limiters_have_independent_hashed_actor_and_ip_layers(
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
            'academic.write',
            $this->request(actorId: 101, ip: '203.0.113.10'),
        );
        $differentActor = $this->limitsFor(
            'academic.write',
            $this->request(actorId: 202, ip: '203.0.113.10'),
        );
        $differentIp = $this->limitsFor(
            'academic.write',
            $this->request(actorId: 101, ip: '203.0.113.11'),
        );

        $this->assertNotSame($baseline[0]->key, $differentActor[0]->key);
        $this->assertSame($baseline[1]->key, $differentActor[1]->key);
        $this->assertSame($baseline[0]->key, $differentIp[0]->key);
        $this->assertNotSame($baseline[1]->key, $differentIp[1]->key);
    }

    public function test_read_write_and_destructive_limiters_do_not_share_buckets(): void
    {
        $request = $this->request(actorId: 101, ip: '203.0.113.10');
        $read = $this->limitsFor('academic.read', $request);
        $write = $this->limitsFor('academic.write', $request);
        $destructive = $this->limitsFor('academic.destructive', $request);

        $this->assertCount(3, array_unique([$read[0]->key, $write[0]->key, $destructive[0]->key]));
        $this->assertCount(3, array_unique([$read[1]->key, $write[1]->key, $destructive[1]->key]));
    }

    public function test_submitted_owner_fields_cannot_change_the_authenticated_actor_key(): void
    {
        $baseline = $this->limitsFor(
            'academic.write',
            $this->request(actorId: 101, ip: '203.0.113.10', submittedUserId: 101),
        );
        $forgedOwner = $this->limitsFor(
            'academic.write',
            $this->request(actorId: 101, ip: '203.0.113.10', submittedUserId: 999),
        );

        $this->assertSame($baseline[0]->key, $forgedOwner[0]->key);
        $this->assertSame($baseline[1]->key, $forgedOwner[1]->key);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function limiterProvider(): iterable
    {
        yield 'read' => ['academic.read', 120];
        yield 'write' => ['academic.write', 60];
        yield 'destructive' => ['academic.destructive', 20];
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

    private function request(
        int $actorId,
        string $ip,
        int $submittedUserId = 101,
    ): Request {
        $user = new User;
        $user->forceFill(['id' => $actorId]);
        $request = Request::create(
            uri: '/api/v1/courses',
            method: 'PUT',
            parameters: ['user_id' => $submittedUserId],
            server: ['REMOTE_ADDR' => $ip],
        );
        $request->setUserResolver(static fn (): User => $user);

        return $request;
    }
}
