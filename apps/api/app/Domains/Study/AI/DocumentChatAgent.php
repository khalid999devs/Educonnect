<?php

declare(strict_types=1);

namespace App\Domains\Study\AI;

use App\Domains\Study\Contracts\DocumentChatProvider;
use App\Support\Ai\AiFeature;
use App\Support\Ai\OpenAiClient;

/**
 * OpenAI-backed document chat.
 *
 * The agent owns transport and nothing else. The prompt lives in
 * BuildDocumentChatMessages, and the kill switch, per-feature circuit breaker,
 * timing, telemetry and failure ladder live in AiAgentRunner, which is the only
 * thing that ever calls this class. Passing AiFeature::DocumentChat down to the
 * client is what keeps this capability behind its own breaker, so a flood of
 * failing chats cannot short-circuit intake classification or the Copilot.
 *
 * There is no bounded output-retry loop here, unlike the structured agents: a
 * chat reply has no schema to fail, so any error is a provider fault and is
 * handed straight to the runner, which reports it honestly.
 */
final readonly class DocumentChatAgent implements DocumentChatProvider
{
    public function __construct(private OpenAiClient $client) {}

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return AiFeature::DocumentChat->model();
    }

    /** @param list<array{role: string, content: string}> $messages */
    public function reply(array $messages): string
    {
        return $this->client->chat($this->model(), $messages, AiFeature::DocumentChat);
    }
}
