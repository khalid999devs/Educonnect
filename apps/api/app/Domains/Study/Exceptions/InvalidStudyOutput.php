<?php

declare(strict_types=1);

namespace App\Domains\Study\Exceptions;

use App\Support\Ai\Exceptions\InvalidAiOutput;

/**
 * A study generation payload that failed StudyOutputSchemaV1.
 *
 * Extending InvalidAiOutput is what drives retry semantics and must not be
 * conflated: this means the provider answered and answered badly, so a bounded
 * output retry is worthwhile. Any other Throwable abandons the provider.
 */
final class InvalidStudyOutput extends InvalidAiOutput {}
