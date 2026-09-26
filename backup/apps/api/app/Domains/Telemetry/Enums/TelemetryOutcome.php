<?php

declare(strict_types=1);

namespace App\Domains\Telemetry\Enums;

enum TelemetryOutcome: string
{
    /** The operation completed as intended. */
    case Success = 'success';

    /** The operation failed outright. */
    case Failure = 'failure';

    /** The primary path failed and a deterministic fallback answered instead. */
    case Fallback = 'fallback';

    /** The operation ran in a reduced mode (for example, a circuit breaker was open). */
    case Degraded = 'degraded';
}
