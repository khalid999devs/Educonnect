<?php

declare(strict_types=1);

namespace App\Support\Ai;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * Minimal OpenAI chat-completions client. Callers own prompt construction
 * and output validation; this class owns transport, bounds, and redaction
 * (errors never include prompt or completion content).
 */
final readonly class OpenAiClient
{
    public function __construct(private HttpFactory $http) {}

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

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => max(1, (int) config('ai.openai.max_output_tokens')),
        ];

        if ($jsonObject) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->http
            ->baseUrl((string) config('ai.openai.base_url'))
            ->withToken(trim($key))
            ->acceptJson()
            ->timeout(max(1, (int) config('ai.openai.timeout_seconds')))
            ->connectTimeout(5)
            ->post('/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException("OpenAI request failed with status {$response->status()}.");
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI returned an empty completion.');
        }

        return $content;
    }
}
