<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Support\CircuitBreaker;
use App\Support\Exceptions\CircuitBreakerOpen;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;
use Throwable;

/**
 * Minimal OpenAI chat-completions client. Callers own prompt construction
 * and output validation; this class owns transport, bounds, redaction
 * (errors never include prompt or completion content), and the shared
 * circuit breaker that protects a failing provider from being hammered.
 */
final readonly class OpenAiClient
{
    private const BREAKER_KEY = 'ai.openai';

    public function __construct(
        private HttpFactory $http,
        private CircuitBreaker $breaker,
    ) {}

    public static function configured(): bool
    {
        $key = config('ai.openai.api_key');

        return is_string($key) && trim($key) !== '';
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(string $model, array $messages, bool $jsonObject = false): string
    {
        $key = config('ai.openai.api_key');

        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('OpenAI is not configured.');
        }

        $breakerEnabled = (bool) config('ai.circuit_breaker.enabled', true);

        if ($breakerEnabled && ! $this->breaker->isAvailable(self::BREAKER_KEY)) {
            throw new CircuitBreakerOpen('The OpenAI circuit breaker is open.');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => max(1, (int) config('ai.openai.max_output_tokens')),
        ];

        $reasoningEffort = trim((string) config('ai.openai.reasoning_effort', ''));

        if ($reasoningEffort !== '') {
            $payload['reasoning_effort'] = $reasoningEffort;
        }

        if ($jsonObject) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = $this->http
                ->baseUrl((string) config('ai.openai.base_url'))
                ->withToken(trim($key))
                ->acceptJson()
                ->timeout(max(1, (int) config('ai.openai.timeout_seconds')))
                ->connectTimeout(5)
                ->post('/chat/completions', $payload);
        } catch (Throwable $exception) {
            $this->trip($breakerEnabled);
            throw new RuntimeException('OpenAI request failed.', 0, $exception);
        }

        if ($response->failed()) {
            $this->trip($breakerEnabled);
            throw new RuntimeException("OpenAI request failed with status {$response->status()}.");
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            $this->trip($breakerEnabled);
            throw new RuntimeException('OpenAI returned an empty completion.');
        }

        if ($breakerEnabled) {
            $this->breaker->recordSuccess(self::BREAKER_KEY);
        }

        return $content;
    }

    private function trip(bool $breakerEnabled): void
    {
        if (! $breakerEnabled) {
            return;
        }

        $this->breaker->recordFailure(
            self::BREAKER_KEY,
            (int) config('ai.circuit_breaker.failure_threshold', 5),
            (int) config('ai.circuit_breaker.cooldown_seconds', 60),
        );
    }
}
