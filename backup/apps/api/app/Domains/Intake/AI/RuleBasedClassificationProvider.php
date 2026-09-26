<?php

declare(strict_types=1);

namespace App\Domains\Intake\AI;

use App\Domains\Intake\Contracts\AIProvider;
use App\Support\Ai\BoundedText;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Deterministic non-AI fallback classifier. It only pattern-matches dates,
 * academic keywords, and the user's own course names; because it never
 * interprets instructions, prompt-injection text in the document is inert.
 */
final class RuleBasedClassificationProvider implements AIProvider
{
    private const TASK_KEYWORDS = [
        'assignment', 'homework', 'due', 'deadline', 'submit', 'submission',
        'exam', 'quiz', 'midterm', 'final', 'project', 'presentation', 'report',
    ];

    public function name(): string
    {
        return 'rule_based';
    }

    public function model(): string
    {
        return 'deterministic-rules-1';
    }

    /** @return array<string, mixed> */
    public function classify(ClassificationRequest $request): array
    {
        $text = $request->extractedText;
        $course = $this->matchCourse($text, $request);

        // The captured document itself is always worth keeping. This one
        // suggestion is never conditional, so a capture is never a dead end with
        // "nothing to review": it always has a way into the Second Brain and the
        // focused workspace. It mirrors what the AI classifier already offers.
        $suggestions = [
            [
                'kind' => 'knowledge_item',
                'title' => $this->titleFromLine($this->firstLine($text)),
                'description' => $request->context,
                'due_at' => null,
                'course_public_id' => $course,
                'url' => $request->sourceUrl,
                'confidence' => 0.5,
                'reason' => 'Keeping this in your Second Brain lets you open it in the focused workspace and work through it.',
            ],
        ];

        // One slot is already spent on the knowledge item, so the task budget
        // leaves room for it (and for the source-link resource below).
        foreach ($this->taskLines($text, $request->maxSuggestions - 1) as $line) {
            $suggestions[] = [
                'kind' => 'task',
                'title' => $this->titleFromLine($line['line']),
                'description' => null,
                'due_at' => $line['due_at'],
                'course_public_id' => $course,
                'url' => null,
                'confidence' => $line['due_at'] !== null ? 0.7 : 0.4,
                'reason' => $line['due_at'] !== null
                    ? 'The document mentions an academic keyword next to the date '.$line['due_at'].'.'
                    : 'The document mentions an academic task keyword on this line.',
            ];
        }

        if ($request->sourceUrl !== null && count($suggestions) < $request->maxSuggestions) {
            $suggestions[] = [
                'kind' => 'resource',
                'title' => $this->titleFromLine($this->firstLine($text)),
                'description' => $request->context,
                'due_at' => null,
                'course_public_id' => $course,
                'url' => $request->sourceUrl,
                'confidence' => 0.5,
                'reason' => 'Saving the ingested source link keeps it findable in your workspace.',
            ];
        }

        return ['suggestions' => array_slice($suggestions, 0, $request->maxSuggestions)];
    }

    /** @return list<array{line: string, due_at: string|null}> */
    private function taskLines(string $text, int $limit): array
    {
        $lines = preg_split('/\n+/', $text) ?: [];
        $matches = [];

        foreach ($lines as $line) {
            if (count($matches) >= max(0, $limit - 1)) {
                break;
            }

            $lower = mb_strtolower($line);
            $hasKeyword = false;

            foreach (self::TASK_KEYWORDS as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $hasKeyword = true;

                    break;
                }
            }

            if (! $hasKeyword) {
                continue;
            }

            $matches[] = ['line' => $line, 'due_at' => $this->dateFromLine($line)];
        }

        return $matches;
    }

    private function dateFromLine(string $line): ?string
    {
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $line, $iso) === 1) {
            return $this->boundedDate($iso[1]);
        }

        if (preg_match(
            '/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2}),?\s+(\d{4})\b/i',
            $line,
            $written,
        ) === 1) {
            try {
                return $this->boundedDate(
                    CarbonImmutable::createFromFormat('!F j Y', $written[1].' '.$written[2].' '.$written[3])
                        ->format('Y-m-d'),
                );
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function boundedDate(string $date): ?string
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (Throwable) {
            return null;
        }

        if ($parsed->format('Y-m-d') !== $date
            || $parsed->lessThan(now()->subYear())
            || $parsed->greaterThan(now()->addYears(3))) {
            return null;
        }

        return $date;
    }

    private function matchCourse(string $text, ClassificationRequest $request): ?string
    {
        $lower = mb_strtolower($text);

        foreach ($request->courses as $course) {
            $code = $course['code'];

            if (is_string($code) && $code !== '' && str_contains($lower, mb_strtolower($code))) {
                return $course['public_id'];
            }
        }

        foreach ($request->courses as $course) {
            if (mb_strlen($course['title']) >= 6 && str_contains($lower, mb_strtolower($course['title']))) {
                return $course['public_id'];
            }
        }

        return null;
    }

    private function firstLine(string $text): string
    {
        $lines = preg_split('/\n+/', trim($text)) ?: [];

        return $lines[0] ?? 'Ingested source';
    }

    private function titleFromLine(string $line): string
    {
        return BoundedText::titleText($line, 160, 'Review the ingested source');
    }
}
