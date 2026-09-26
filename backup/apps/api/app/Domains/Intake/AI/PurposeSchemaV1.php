<?php

declare(strict_types=1);

namespace App\Domains\Intake\AI;

use App\Domains\Intake\Enums\IntakePurpose;
use App\Domains\Intake\Exceptions\InvalidPurposeOutput;
use App\Support\Ai\BoundedText;
use InvalidArgumentException;

/**
 * Versioned structured-output schema for purpose routing, built structurally on
 * SuggestionSchemaV1: an explicit key whitelist, an explicit enum whitelist,
 * bounded plain text, and a bounded numeric range. Anything a prompt-injected
 * document persuaded a provider to emit - an invented purpose, an extra key,
 * markup in the reason - is rejected here and never reaches the database.
 *
 * `confidence` and `reason` are validated and then deliberately discarded: only
 * the purpose is persisted, but requiring the provider to justify its answer
 * keeps a bare guess from passing as a structured answer, and validating them
 * means a malformed payload is rejected rather than silently half-read.
 */
final class PurposeSchemaV1
{
    public const VERSION = 'v1';

    private const ALLOWED_KEYS = ['purpose', 'confidence', 'reason'];

    /**
     * @param  array<string, mixed>  $raw
     *
     * @throws InvalidPurposeOutput
     */
    public function validate(array $raw): IntakePurpose
    {
        if (array_diff(array_keys($raw), self::ALLOWED_KEYS) !== []) {
            throw new InvalidPurposeOutput('the payload contains unknown keys');
        }

        $purpose = $raw['purpose'] ?? null;

        if (! is_string($purpose)) {
            throw new InvalidPurposeOutput('the purpose must be a string');
        }

        $routed = IntakePurpose::tryFrom($purpose);

        if (! $routed instanceof IntakePurpose) {
            throw new InvalidPurposeOutput('unknown purpose');
        }

        $this->validConfidence($raw['confidence'] ?? null);
        $this->validReason($raw['reason'] ?? null);

        return $routed;
    }

    private function validConfidence(mixed $value): void
    {
        if (! is_float($value) && ! is_int($value)) {
            throw new InvalidPurposeOutput('confidence must be numeric');
        }

        $confidence = (float) $value;

        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new InvalidPurposeOutput('confidence must stay between zero and one');
        }
    }

    private function validReason(mixed $value): void
    {
        if (! is_string($value)) {
            throw new InvalidPurposeOutput('the reason must be a string');
        }

        try {
            BoundedText::plainText($value, 500);
        } catch (InvalidArgumentException) {
            throw new InvalidPurposeOutput('the reason must be bounded plain text');
        }
    }
}
