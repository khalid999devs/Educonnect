<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Domains\Telemetry\Enums\TelemetryOutcome;
use App\Domains\Telemetry\Support\TelemetryRecorder;
use App\Support\Ai\Exceptions\AiFeatureDisabled;
use App\Support\Exceptions\CircuitBreakerOpen;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The shared execution envelope every AI agent runs inside. It owns the
 * cross-cutting concerns - kill switch, timing, telemetry, the exception
 * ladder, and fallback ordering - so no agent re-implements them and no two
 * capabilities drift apart. The semantics (prompt, schema, fallback
 * implementation) stay in the domain-owned agents.
 *
 * Redaction is a hard rule here: prompts and completions are never logged and
 * never recorded as telemetry metadata.
 */
final readonly class AiAgentRunner
{
    public function __construct(private TelemetryRecorder $telemetry) {}

    /**
     * Run one AI capability with its deterministic fallback, if it has one.
     *
     * A null fallback is the honest-failure contract: study material and
     * document answers degrade to an error rather than to fabricated content.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $primary  the remote attempt
     * @param  (callable(): TValue)|null  $fallback  the deterministic attempt; null rethrows instead
     * @return AiCallResult<TValue>
     *
     * @throws AiFeatureDisabled when the capability's kill switch is off
     */
    public function run(
        AiFeature $feature,
        string $providerName,
        callable $primary,
        ?callable $fallback = null,
        string $fallbackProvider = 'deterministic',
        string $fallbackModel = 'deterministic',
    ): AiCallResult {
        if (! $feature->enabled()) {
            throw new AiFeatureDisabled($feature);
        }

        $model = $feature->model();
        $startedAt = hrtime(true);

        try {
            $value = $primary();
            $latencyMs = $this->elapsedMs($startedAt);

            $this->telemetry->recordAiCall(
                $feature->telemetryName(),
                TelemetryOutcome::Success,
                $latencyMs,
                ['provider' => $providerName, 'model' => $model],
            );

            return new AiCallResult($value, $providerName, $model, $latencyMs, TelemetryOutcome::Success);
        } catch (CircuitBreakerOpen $exception) {
            // Caught before the generic Throwable, always: a short-circuited
            // call is a deliberate reduced mode, not a provider fault.
            $outcome = TelemetryOutcome::Degraded;
        } catch (Throwable $exception) {
            $outcome = TelemetryOutcome::Failure;

            Log::warning("ai.{$feature->value}.failed", [
                'operation' => $feature->telemetryName(),
                'provider' => $providerName,
                'model' => $model,
                'exception' => $exception::class,
            ]);
        }

        $this->telemetry->recordAiCall(
            $feature->telemetryName(),
            $outcome,
            $this->elapsedMs($startedAt),
            ['provider' => $providerName, 'model' => $model],
        );

        if ($fallback === null) {
            throw $exception;
        }

        $fallbackStartedAt = hrtime(true);
        $fallbackValue = $fallback();
        $fallbackLatencyMs = $this->elapsedMs($fallbackStartedAt);

        $this->telemetry->recordAiCall(
            $feature->telemetryName(),
            TelemetryOutcome::Fallback,
            $fallbackLatencyMs,
            ['provider' => $fallbackProvider, 'model' => $fallbackModel],
        );

        return new AiCallResult(
            $fallbackValue,
            $fallbackProvider,
            $fallbackModel,
            $fallbackLatencyMs,
            TelemetryOutcome::Fallback,
        );
    }

    private function elapsedMs(float|int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
