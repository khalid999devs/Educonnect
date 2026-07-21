<?php

declare(strict_types=1);

namespace App\Support\Ai\Exceptions;

use RuntimeException;

/**
 * Base for every versioned schema rejection (`*SchemaV1::validate()`).
 *
 * The distinction drives retry semantics and must not be conflated: an
 * InvalidAiOutput means the provider answered but the answer failed structural
 * validation, so a bounded output retry is worthwhile. Any other Throwable
 * means the provider itself is unhealthy, so that provider is abandoned
 * immediately and the deterministic path (or an honest failure) takes over.
 */
abstract class InvalidAiOutput extends RuntimeException {}
