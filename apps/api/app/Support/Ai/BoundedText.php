<?php

declare(strict_types=1);

namespace App\Support\Ai;

use InvalidArgumentException;

/**
 * The one place model-supplied and document-supplied text is bounded.
 *
 * Three private reimplementations of this logic existed across the intake
 * schema, the Copilot controller, and the deterministic classifier; every new
 * versioned schema would have added a fourth. They are consolidated here so a
 * hardening fix lands once for every capability.
 *
 * The three shapes are deliberately distinct and must not be merged:
 * - plainText  rejects  (strict validation of a structured field)
 * - freeText   sanitises (a conversational reply is bounded, never refused)
 * - titleText  repairs   (a heuristic title always yields something usable)
 */
final class BoundedText
{
    /** Control characters that no bounded text may ever carry. */
    private const CONTROL_CHARACTERS = '/[\x00-\x1F\x7F]/u';

    /**
     * Control characters stripped from free text. Tab, line feed, and carriage
     * return survive because a chat reply legitimately contains them.
     */
    private const STRIPPABLE_CONTROL_CHARACTERS = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u';

    /**
     * Validate a structured plain-text field: trimmed, non-empty, within the
     * bound, free of control characters, and free of angle brackets so the
     * value cannot carry markup into any renderer.
     *
     * @throws InvalidArgumentException when the value is not bounded plain text
     */
    public static function plainText(string $value, int $max): string
    {
        $value = trim($value);

        if ($value === ''
            || mb_strlen($value) > $max
            || preg_match(self::CONTROL_CHARACTERS, $value) === 1
            || str_contains($value, '<')
            || str_contains($value, '>')) {
            throw new InvalidArgumentException('The value is not bounded plain text.');
        }

        return $value;
    }

    /**
     * Bound conversational free text: trim, strip control characters, hard-cap.
     * This never throws - a reply that is too long is truncated, not refused.
     */
    public static function freeText(string $value, int $max): string
    {
        $clean = preg_replace(self::STRIPPABLE_CONTROL_CHARACTERS, '', trim($value));

        return mb_substr($clean ?? '', 0, $max);
    }

    /**
     * Derive a display title from an arbitrary line of extracted text. Angle
     * brackets and control characters become spaces, runs of whitespace
     * collapse, an empty result becomes the caller's fallback, and an
     * over-length result is ellipsised within the bound.
     */
    public static function titleText(string $value, int $max, string $fallback): string
    {
        $title = trim((string) preg_replace('/[<>\x00-\x1F\x7F]/u', ' ', $value));
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));

        if ($title === '') {
            $title = $fallback;
        }

        return mb_strlen($title) > $max ? mb_substr($title, 0, $max - 3).'...' : $title;
    }
}
