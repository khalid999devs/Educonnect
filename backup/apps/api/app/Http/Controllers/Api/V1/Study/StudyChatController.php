<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Study;

use App\Domains\Study\Contracts\DocumentChatProvider;
use App\Domains\Study\Queries\BuildDocumentChatMessages;
use App\Http\Requests\Api\V1\Study\StudyChatRequest;
use App\Support\Ai\AiAgentRunner;
use App\Support\Ai\AiFeature;
use App\Support\Ai\BoundedText;
use App\Support\Ai\Exceptions\AiFeatureDisabled;
use App\Support\Ai\OpenAiClient;
use App\Support\ApiErrorCode;
use App\Support\ApiResponse;
use App\Support\Exceptions\CircuitBreakerOpen;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * One grounded chat turn about one document.
 *
 * The ladder mirrors CopilotMessageController exactly - configured gate,
 * CircuitBreakerOpen as Degraded, any other Throwable as Failure, Success
 * telemetry, then a bounded reply carrying the model and an AI disclaimer - but
 * the telemetry and the failure ladder are not re-implemented here. They are
 * AiAgentRunner's, which every AI capability runs inside; this controller only
 * translates the runner's outcomes into the three 503 shapes the client sees.
 *
 * The fallback is deliberately null. Document chat degrades honestly: an
 * invented answer about a student's own source material is strictly worse than
 * no answer, so a dead provider produces a 503, never fabricated text.
 *
 * Ownership is settled inside BuildDocumentChatMessages, which runs before the
 * provider is ever called, so a request for another student's document cannot
 * spend a token of AI budget, let alone read the document.
 */
final class StudyChatController
{
    private const DISCLAIMER = 'AI-generated from this document. Verify important details against the document itself.';

    private const MAX_REPLY_CHARACTERS = 4000;

    public function __invoke(
        StudyChatRequest $request,
        BuildDocumentChatMessages $builder,
        DocumentChatProvider $provider,
        AiAgentRunner $runner,
    ): JsonResponse {
        if (! OpenAiClient::configured()) {
            return $this->unavailable('Document chat is not configured on this server.');
        }

        $messages = $builder->execute(
            $request->authenticatedUser(),
            $request->itemPublicId(),
            $request->history(),
            $request->message(),
        );

        try {
            $result = $runner->run(
                AiFeature::DocumentChat,
                $provider->name(),
                static fn (): string => $provider->reply($messages),
            );
        } catch (AiFeatureDisabled) {
            // An operator decision, never a provider fault, so it is never retried.
            return $this->unavailable('Document chat is switched off on this server.');
        } catch (CircuitBreakerOpen) {
            // Caught before the generic Throwable, always: the runner already
            // recorded this as Degraded rather than as a provider failure.
            return $this->unavailable('Document chat is briefly unavailable while the AI provider recovers. Try again shortly.');
        } catch (Throwable) {
            // The runner has already recorded Failure and logged the exception
            // class without the prompt or the completion; nothing more is said
            // to the client than that the provider is down.
            return $this->unavailable('The document chat provider is unavailable right now. Try again shortly.');
        }

        return ApiResponse::success([
            'study_chat' => [
                'reply' => BoundedText::freeText($result->value, self::MAX_REPLY_CHARACTERS),
                'model' => $result->model,
                'disclaimer' => self::DISCLAIMER,
            ],
        ]);
    }

    private function unavailable(string $message): JsonResponse
    {
        return ApiResponse::error(ApiErrorCode::ServiceUnavailable, $message, 503);
    }
}
