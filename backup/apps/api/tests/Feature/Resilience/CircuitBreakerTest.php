<?php

declare(strict_types=1);

namespace Tests\Feature\Resilience;

use App\Support\CircuitBreaker;
use Tests\TestCase;

final class CircuitBreakerTest extends TestCase
{
    private const KEY = 'test.service';

    public function test_it_opens_after_the_failure_threshold(): void
    {
        $breaker = new CircuitBreaker;
        self::assertTrue($breaker->isAvailable(self::KEY));

        $breaker->recordFailure(self::KEY, 3, 60);
        $breaker->recordFailure(self::KEY, 3, 60);
        self::assertTrue($breaker->isAvailable(self::KEY));

        $breaker->recordFailure(self::KEY, 3, 60);
        self::assertFalse($breaker->isAvailable(self::KEY));
    }

    public function test_it_closes_again_after_the_cooldown(): void
    {
        $breaker = new CircuitBreaker;
        $breaker->recordFailure(self::KEY, 1, 60);
        self::assertFalse($breaker->isAvailable(self::KEY));

        $this->travel(61)->seconds(function () use ($breaker): void {
            self::assertTrue($breaker->isAvailable(self::KEY));
        });
    }

    public function test_a_success_resets_the_failure_run(): void
    {
        $breaker = new CircuitBreaker;
        $breaker->recordFailure(self::KEY, 3, 60);
        $breaker->recordFailure(self::KEY, 3, 60);
        $breaker->recordSuccess(self::KEY);

        // The two prior failures were cleared, so it takes three fresh ones.
        $breaker->recordFailure(self::KEY, 3, 60);
        $breaker->recordFailure(self::KEY, 3, 60);
        self::assertTrue($breaker->isAvailable(self::KEY));
        $breaker->recordFailure(self::KEY, 3, 60);
        self::assertFalse($breaker->isAvailable(self::KEY));
    }
}
