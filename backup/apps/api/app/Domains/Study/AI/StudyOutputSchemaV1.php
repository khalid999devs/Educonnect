<?php

declare(strict_types=1);

namespace App\Domains\Study\AI;

use App\Domains\Study\Exceptions\InvalidStudyOutput;
use App\Support\Ai\BoundedText;
use InvalidArgumentException;

/**
 * Versioned structured-output schema for summaries, topic explanations, and
 * quick-learn walkthroughs. Built structurally on SuggestionSchemaV1: a
 * VERSION, an explicit key whitelist at every level, bounded text everywhere,
 * and hard counts.
 *
 * Everything a prompt-injected document persuaded a provider to emit - an extra
 * top-level key, markup in a heading, a hundred sections - is rejected here and
 * never reaches the database or a renderer.
 *
 * The validated array is returned in a fixed key order so the persisted jsonb
 * payload is stable across runs.
 */
final class StudyOutputSchemaV1
{
    public const VERSION = 'v1';

    public const MAX_SECTIONS = 12;

    public const MAX_KEY_POINTS = 12;

    private const ALLOWED_KEYS = ['title', 'overview', 'sections', 'key_points'];

    private const ALLOWED_SECTION_KEYS = ['heading', 'body'];

    private const MAX_TITLE_CHARACTERS = 160;

    private const MAX_OVERVIEW_CHARACTERS = 1_200;

    private const MAX_HEADING_CHARACTERS = 160;

    private const MAX_BODY_CHARACTERS = 4_000;

    private const MAX_KEY_POINT_CHARACTERS = 300;

    /**
     * @param  array<string, mixed>  $raw
     * @return array{title: string, overview: string, sections: list<array{heading: string, body: string}>, key_points: list<string>}
     *
     * @throws InvalidStudyOutput
     */
    public function validate(array $raw): array
    {
        if (array_diff(array_keys($raw), self::ALLOWED_KEYS) !== []) {
            throw new InvalidStudyOutput('the payload contains unknown keys');
        }

        return [
            'title' => $this->plainText($raw['title'] ?? null, self::MAX_TITLE_CHARACTERS, 'title'),
            'overview' => $this->prose($raw['overview'] ?? null, self::MAX_OVERVIEW_CHARACTERS, 'overview'),
            'sections' => $this->sections($raw['sections'] ?? null),
            'key_points' => $this->keyPoints($raw['key_points'] ?? null),
        ];
    }

    /** @return list<array{heading: string, body: string}> */
    private function sections(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            throw new InvalidStudyOutput('sections must be a non-empty list');
        }

        if (count($value) > self::MAX_SECTIONS) {
            throw new InvalidStudyOutput('too many sections');
        }

        $sections = [];

        foreach ($value as $section) {
            if (! is_array($section)) {
                throw new InvalidStudyOutput('a section must be an object');
            }

            if (array_diff(array_keys($section), self::ALLOWED_SECTION_KEYS) !== []) {
                throw new InvalidStudyOutput('a section contains unknown keys');
            }

            $sections[] = [
                'heading' => $this->plainText($section['heading'] ?? null, self::MAX_HEADING_CHARACTERS, 'heading'),
                'body' => $this->prose($section['body'] ?? null, self::MAX_BODY_CHARACTERS, 'body'),
            ];
        }

        return $sections;
    }

    /** @return list<string> */
    private function keyPoints(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidStudyOutput('key points must be a list');
        }

        if (count($value) > self::MAX_KEY_POINTS) {
            throw new InvalidStudyOutput('too many key points');
        }

        return array_map(
            fn (mixed $point): string => $this->plainText($point, self::MAX_KEY_POINT_CHARACTERS, 'key point'),
            $value,
        );
    }

    /**
     * A single-line structured field: the shared strict rule, re-labelled with
     * this schema's field names.
     */
    private function plainText(mixed $value, int $max, string $field): string
    {
        if (! is_string($value)) {
            throw new InvalidStudyOutput("the {$field} must be a string");
        }

        try {
            return BoundedText::plainText($value, $max);
        } catch (InvalidArgumentException) {
            throw new InvalidStudyOutput("the {$field} must be bounded plain text");
        }
    }

    /**
     * Multi-paragraph prose. Line breaks are legitimate here, so the shared
     * free-text sanitiser strips the other control characters and the angle
     * brackets are rejected outright rather than escaped, keeping the payload
     * inert in every renderer.
     */
    private function prose(mixed $value, int $max, string $field): string
    {
        if (! is_string($value)) {
            throw new InvalidStudyOutput("the {$field} must be a string");
        }

        $clean = BoundedText::freeText($value, $max + 1);

        if ($clean === ''
            || mb_strlen($clean) > $max
            || str_contains($clean, '<')
            || str_contains($clean, '>')) {
            throw new InvalidStudyOutput("the {$field} must be bounded plain text");
        }

        return $clean;
    }
}
