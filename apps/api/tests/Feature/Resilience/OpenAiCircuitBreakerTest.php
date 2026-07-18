<?php

declare(strict_types=1);

namespace Tests\Feature\Resilience;

use App\Support\Ai\OpenAiClient;
use App\Support\Exceptions\CircuitBreakerOpen;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class OpenAiCircuitBreakerTest extends TestCase
{
    /** @var list<array{role: string, content: string}> */
    private const MESSAGES = [['role' => 'user', 'content' => 'hi']];

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'ai.openai.api_key' => 'sk-test-key',
            'ai.openai.base_url' => 'https://api.openai.com/v1',
            'ai.circuit_breaker.enabled' => true,
            'ai.circuit_breaker.failure_threshold' => 3,
            'ai.circuit_breaker.cooldown_seconds' => 60,
        ]);
    }

    public function test_repeated_failures_open_the_breaker_and_short_circuit(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $client = app(OpenAiClient::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $client->chat('gpt-5-mini', self::MESSAGES);
                self::fail('Expected the upstream failure to throw.');
            } catch (RuntimeException $exception) {
                self::assertNotInstanceOf(CircuitBreakerOpen::class, $exception);
            }
        }

        // The breaker is now open: the next call short-circuits with no request.
        try {
            $client->chat('gpt-5-mini', self::MESSAGES);
            self::fail('Expected the open breaker to short-circuit.');
        } catch (CircuitBreakerOpen) {
            // expected
        }

        Http::assertSentCount(3);
    }

    public function test_the_breaker_can_be_disabled(): void
    {
        config()->set('ai.circuit_breaker.enabled', false);
        Http::fake(['*' => Http::response('', 500)]);
        $client = app(OpenAiClient::class);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $client->chat('gpt-5-mini', self::MESSAGES);
            } catch (RuntimeException $exception) {
                self::assertNotInstanceOf(CircuitBreakerOpen::class, $exception);
            }
        }

        // Every attempt reached the provider; nothing was short-circuited.
        Http::assertSentCount(5);
    }
}
