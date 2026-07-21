<?php

declare(strict_types=1);

namespace App\Domains\Intake\AI;

use App\Domains\Intake\Exceptions\InvalidSuggestionOutput;
use App\Support\Ai\BoundedText;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * The v1 structured-output schema, retained only to read intake rows persisted
 * under schema_version 'v1' and for the tests that pin its behaviour directly.
 * SuggestionSchemaV2 is the current classification path, and new provider
 * responses are validated there. The validation here stays authoritative for
 * those historical rows: unknown keys, unbounded text, unsafe URLs, unknown
 * courses, out-of-range confidence, or excess suggestions are rejected -
 * including anything a prompt-injected document convinced a provider to emit.
 */
final class SuggestionSchemaV1
{
    public const VERSION = 'v1';

    private const ALLOWED_KEYS = [
        'kind', 'title', 'description', 'due_at', 'course_public_id', 'url', 'confidence', 'reason',
    ];

    /**
     * @param  array<string, mixed>  $raw
     * @return list<array{kind: string, title: string, description: string|null, due_at: string|null, course_public_id: string|null, url: string|null, confidence: float, reason: string}>
     */
    public function validate(array $raw, ClassificationRequest $request): array
    {
        $suggestions = $raw['suggestions'] ?? null;

        if (! is_array($suggestions) || array_keys($raw) !== ['suggestions'] || ! array_is_list($suggestions)) {
            throw new InvalidSuggestionOutput('the payload must contain only a suggestions list');
        }

        if (count($suggestions) > $request->maxSuggestions) {
            throw new InvalidSuggestionOutput('too many suggestions');
        }

        $knownCourses = array_column($request->courses, 'public_id');
        $validated = [];

        foreach ($suggestions as $suggestion) {
            if (! is_array($suggestion)) {
                throw new InvalidSuggestionOutput('a suggestion must be an object');
            }

            if (array_diff(array_keys($suggestion), self::ALLOWED_KEYS) !== []) {
                throw new InvalidSuggestionOutput('a suggestion contains unknown keys');
            }

            $kind = $suggestion['kind'] ?? null;

            if (! in_array($kind, ['task', 'resource'], true)) {
                throw new InvalidSuggestionOutput('unknown suggestion kind');
            }

            $title = $this->boundedPlainText($suggestion['title'] ?? null, 160, 'title');
            $description = ($suggestion['description'] ?? null) === null
                ? null
                : $this->boundedPlainText($suggestion['description'], 2000, 'description');
            $reason = $this->boundedPlainText($suggestion['reason'] ?? null, 1000, 'reason');

            $confidence = $suggestion['confidence'] ?? null;

            if (! is_float($confidence) && ! is_int($confidence)) {
                throw new InvalidSuggestionOutput('confidence must be numeric');
            }

            $confidence = (float) $confidence;

            if ($confidence < 0.0 || $confidence > 1.0) {
                throw new InvalidSuggestionOutput('confidence must stay between zero and one');
            }

            $coursePublicId = $suggestion['course_public_id'] ?? null;

            if ($coursePublicId !== null
                && (! is_string($coursePublicId) || ! in_array($coursePublicId, $knownCourses, true))) {
                throw new InvalidSuggestionOutput('a suggestion referenced an unknown course');
            }

            $dueAt = $this->validDueDate($suggestion['due_at'] ?? null, $kind);
            $url = $this->validUrl($suggestion['url'] ?? null, $kind);

            $validated[] = [
                'kind' => $kind,
                'title' => $title,
                'description' => $description,
                'due_at' => $dueAt,
                'course_public_id' => $coursePublicId,
                'url' => $url,
                'confidence' => $confidence,
                'reason' => $reason,
            ];
        }

        return $validated;
    }

    /**
     * Adapts the shared bounding rule to this schema's field-named rejection
     * messages. The rule itself lives once, in BoundedText.
     */
    private function boundedPlainText(mixed $value, int $maxLength, string $field): string
    {
        if (! is_string($value)) {
            throw new InvalidSuggestionOutput("the {$field} must be a string");
        }

        try {
            return BoundedText::plainText($value, $maxLength);
        } catch (InvalidArgumentException) {
            throw new InvalidSuggestionOutput("the {$field} must be bounded plain text");
        }
    }

    private function validDueDate(mixed $value, string $kind): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($kind !== 'task' || ! is_string($value)
            || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            throw new InvalidSuggestionOutput('due dates must be ISO dates on task suggestions');
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            $date = null;
        }

        if (! $date instanceof CarbonImmutable
            || $date->format('Y-m-d') !== $value
            || $date->lessThan(now()->subYear())
            || $date->greaterThan(now()->addYears(3))) {
            throw new InvalidSuggestionOutput('due dates must be real dates within a sane window');
        }

        return $value;
    }

    private function validUrl(mixed $value, string $kind): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($kind !== 'resource'
            || ! is_string($value)
            || strlen($value) > 2048
            || filter_var($value, FILTER_VALIDATE_URL) === false
            || ! str_starts_with($value, 'https://')
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
            throw new InvalidSuggestionOutput('resource URLs must be bounded https links');
        }

        return $value;
    }
}
