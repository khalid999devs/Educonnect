<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Domains\Telemetry\Enums\TelemetryOutcome;

/**
 * The outcome of one AiAgentRunner execution: the value an agent produced plus
 * the provenance a caller must persist or report (which provider answered,
 * under which model, how long it took, and whether the answer came from the
 * primary path, a deterministic fallback, or a degraded mode).
 *
 * @template TValue
 */
final readonly class AiCallResult
{
    /**
     * @param  TValue  $value
     */
    public function __construct(
        public mixed $value,
        public string $provider,
        public string $model,
        public int $latencyMs,
        public TelemetryOutcome $outcome,
    ) {}
}
