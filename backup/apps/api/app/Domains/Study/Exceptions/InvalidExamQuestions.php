<?php

declare(strict_types=1);

namespace App\Domains\Study\Exceptions;

use App\Support\Ai\Exceptions\InvalidAiOutput;

/**
 * An exam-question set that failed ExamQuestionSchemaV1.
 *
 * Exam questions get their own rejection type because their schema is the
 * strictest in the domain: a wrong answer is strictly worse than no answer, so
 * a set whose answer is absent from its own option list is rejected outright
 * rather than repaired.
 */
final class InvalidExamQuestions extends InvalidAiOutput {}
