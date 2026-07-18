<?php

declare(strict_types=1);

namespace App\Domains\Telemetry\Enums;

enum TelemetryType: string
{
    case AiCall = 'ai_call';
    case IntakeJob = 'intake_job';
    case Error = 'error';
    case HttpRequest = 'http_request';
}
