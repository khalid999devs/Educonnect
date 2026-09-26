<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;

/**
 * Raised when a circuit breaker is open, so a caller can distinguish a
 * short-circuited (deliberately skipped) call from a genuine provider failure.
 */
final class CircuitBreakerOpen extends RuntimeException {}
