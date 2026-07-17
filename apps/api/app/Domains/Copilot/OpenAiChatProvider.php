<?php

declare(strict_types=1);

namespace App\Domains\Copilot;

use App\Domains\Copilot\Contracts\ChatProvider;
use App\Support\Ai\OpenAiClient;

final readonly class OpenAiChatProvider implements ChatProvider
{
    public function __construct(private OpenAiClient $client) {}

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) config('ai.models.copilot');
    }

    /** @param list<array{role: string, content: string}> $messages */
    public function reply(array $messages): string
    {
        return $this->client->chat($this->model(), $messages);
    }
}
