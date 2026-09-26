<?php

declare(strict_types=1);

namespace App\Support\Ai\Exceptions;

use App\Support\Ai\AiFeature;
use RuntimeException;

/**
 * Raised when a capability's kill switch (`ai.features.<name>.enabled`) is off.
 * Call sites report this as ApiErrorCode::ServiceUnavailable with a 503; it is
 * an operator decision, never a provider fault, so it is never retried.
 */
final class AiFeatureDisabled extends RuntimeException
{
    public function __construct(public readonly AiFeature $feature)
    {
        parent::__construct("The {$feature->value} AI feature is disabled on this server.");
    }
}
