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
 * (errors never include prompt or completion content), and the circuit
 * breaker that protects a failing provider from being hammered.
 *
 * The breaker is keyed per AiFeature, which is why the feature is required:
 * a flood of failures in one capability must never short-circuit the others.
 * There is exactly one breaker mechanism; no global fallback key survives.
 */
final readonly class OpenAiClient
{
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
    public function chat(string $model, array $messages, AiFeature $feature, bool $jsonObject = false): string
    {
        return $this->chatWithUsage($model, $messages, $feature, $jsonObject)->content;
    }

    /**
     * The same call as chat(), returning the provider's reported token usage
     * alongside the completion for cost accounting.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chatWithUsage(
        string $model,
        array $messages,
        AiFeature $feature,
        bool $jsonObject = false,
    ): OpenAiCompletion {
        $key = config('ai.openai.api_key');

        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('OpenAI is not configured.');
        }

        $breakerKey = $feature->breakerKey();
        $breakerEnabled = (bool) config('ai.circuit_breaker.enabled', true);

        if ($breakerEnabled && ! $this->breaker->isAvailable($breakerKey)) {
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
            $this->trip($breakerKey, $breakerEnabled);
            throw new RuntimeException('OpenAI request failed.', 0, $exception);
        }

        if ($response->failed()) {
            $this->trip($breakerKey, $breakerEnabled);
            throw new RuntimeException("OpenAI request failed with status {$response->status()}.");
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            $this->trip($breakerKey, $breakerEnabled);
            throw new RuntimeException('OpenAI returned an empty completion.');
        }

        if ($breakerEnabled) {
            $this->breaker->recordSuccess($breakerKey);
        }

        return new OpenAiCompletion(
            $content,
            $this->tokenCount($response->json('usage.prompt_tokens')),
            $this->tokenCount($response->json('usage.completion_tokens')),
        );
    }

    private function tokenCount(mixed $value): ?int
    {
        return is_int($value) || is_float($value) ? max(0, (int) $value) : null;
    }

    private function trip(string $breakerKey, bool $breakerEnabled): void
    {
        if (! $breakerEnabled) {
            return;
        }

        $this->breaker->recordFailure(
            $breakerKey,
            (int) config('ai.circuit_breaker.failure_threshold', 5),
            (int) config('ai.circuit_breaker.cooldown_seconds', 60),
        );
    }
}
