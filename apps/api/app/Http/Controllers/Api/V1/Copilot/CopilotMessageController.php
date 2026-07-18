<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Copilot;

use App\Domains\Copilot\Contracts\ChatProvider;
use App\Domains\Copilot\Queries\BuildCopilotMessages;
use App\Domains\Telemetry\Enums\TelemetryOutcome;
use App\Domains\Telemetry\Support\TelemetryRecorder;
use App\Http\Requests\Api\V1\Copilot\CopilotMessageRequest;
use App\Support\Ai\OpenAiClient;
use App\Support\ApiErrorCode;
use App\Support\ApiResponse;
use App\Support\Exceptions\CircuitBreakerOpen;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CopilotMessageController
{
    private const DISCLAIMER = 'AI-generated — verify important details against your own records.';

    public function __invoke(
        CopilotMessageRequest $request,
        BuildCopilotMessages $builder,
        ChatProvider $provider,
        TelemetryRecorder $telemetry,
    ): JsonResponse {
        if (! OpenAiClient::configured()) {
            return ApiResponse::error(
                ApiErrorCode::ServiceUnavailable,
                'The Copilot is not configured on this server.',
                503,
            );
        }

        $messages = $builder->execute(
            $request->authenticatedUser(),
            $request->timezone(),
            $request->history(),
            $request->message(),
        );

        $startedAt = hrtime(true);

        try {
            $reply = $provider->reply($messages);
        } catch (CircuitBreakerOpen) {
            // The breaker is open after repeated provider failures; short-circuit
            // without another upstream call and report the degraded mode.
            $telemetry->recordAiCall(
                'copilot',
                TelemetryOutcome::Degraded,
                (int) ((hrtime(true) - $startedAt) / 1_000_000),
                ['provider' => $provider->name()],
            );

            return ApiResponse::error(
                ApiErrorCode::ServiceUnavailable,
                'The Copilot is briefly unavailable while the AI provider recovers. Try again shortly.',
                503,
            );
        } catch (Throwable $exception) {
            $telemetry->recordAiCall(
                'copilot',
                TelemetryOutcome::Failure,
                (int) ((hrtime(true) - $startedAt) / 1_000_000),
                ['provider' => $provider->name()],
            );
            Log::warning('copilot.reply_failed', [
                'operation' => 'copilot.message',
                'provider' => $provider->name(),
                'exception' => $exception::class,
            ]);

            return ApiResponse::error(
                ApiErrorCode::ServiceUnavailable,
                'The Copilot provider is unavailable right now. Try again shortly.',
                503,
            );
        }

        $telemetry->recordAiCall(
            'copilot',
            TelemetryOutcome::Success,
            (int) ((hrtime(true) - $startedAt) / 1_000_000),
            ['provider' => $provider->name(), 'model' => $provider->model()],
        );

        return ApiResponse::success([
            'copilot' => [
                'reply' => $this->boundedReply($reply),
                'model' => $provider->model(),
                'disclaimer' => self::DISCLAIMER,
            ],
        ]);
    }

    private function boundedReply(string $reply): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', trim($reply));

        return mb_substr($clean ?? '', 0, 4000);
    }
}
