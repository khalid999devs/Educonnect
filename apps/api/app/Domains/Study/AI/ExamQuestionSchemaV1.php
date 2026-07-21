<?php

declare(strict_types=1);

namespace App\Domains\Study\AI;

use App\Domains\Study\Exceptions\InvalidExamQuestions;
use App\Support\Ai\BoundedText;
use InvalidArgumentException;

/**
 * Versioned structured-output schema for exam-question sets, built structurally
 * on SuggestionSchemaV1 and deliberately the strictest validator in the domain.
 *
 * A wrong exam answer is worse than no exam answer, so this schema refuses to
 * repair anything. A multiple-choice question whose stated answer is not one of
 * its own options is a contradiction the model produced, and the whole set is
 * rejected rather than shipped with one broken question.
 */
final class ExamQuestionSchemaV1
{
    public const VERSION = 'v1';

    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 6;

    private const ALLOWED_KEYS = ['questions'];

    private const ALLOWED_QUESTION_KEYS = ['prompt', 'options', 'answer', 'explanation'];

    private const MAX_PROMPT_CHARACTERS = 600;

    private const MAX_OPTION_CHARACTERS = 300;

    private const MAX_ANSWER_CHARACTERS = 600;

    private const MAX_EXPLANATION_CHARACTERS = 1_200;

    /**
     * @param  array<string, mixed>  $raw
     * @return array{questions: list<array{prompt: string, options: list<string>|null, answer: string, explanation: string}>}
     *
     * @throws InvalidExamQuestions
     */
    public function validate(array $raw, int $maxQuestions): array
    {
        if (array_keys($raw) !== self::ALLOWED_KEYS) {
            throw new InvalidExamQuestions('the payload must contain only a questions list');
        }

        $questions = $raw['questions'] ?? null;

        if (! is_array($questions) || ! array_is_list($questions) || $questions === []) {
            throw new InvalidExamQuestions('questions must be a non-empty list');
        }

        if (count($questions) > $maxQuestions) {
            throw new InvalidExamQuestions('too many questions');
        }

        $validated = [];

        foreach ($questions as $question) {
            if (! is_array($question)) {
                throw new InvalidExamQuestions('a question must be an object');
            }

            if (array_diff(array_keys($question), self::ALLOWED_QUESTION_KEYS) !== []) {
                throw new InvalidExamQuestions('a question contains unknown keys');
            }

            $options = $this->options($question['options'] ?? null);
            $answer = $this->plainText($question['answer'] ?? null, self::MAX_ANSWER_CHARACTERS, 'answer');

            if ($options !== null && ! in_array($answer, $options, true)) {
                throw new InvalidExamQuestions('the answer must be one of the question options');
            }

            $validated[] = [
                'prompt' => $this->plainText($question['prompt'] ?? null, self::MAX_PROMPT_CHARACTERS, 'prompt'),
                'options' => $options,
                'answer' => $answer,
                'explanation' => $this->explanation($question['explanation'] ?? null),
            ];
        }

        return ['questions' => $validated];
    }

    /**
     * Null means an open-response question, which is legitimate. A list means
     * multiple choice, and then it must be a real one: at least two distinct
     * options, at most six.
     *
     * @return list<string>|null
     */
    private function options(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidExamQuestions('options must be a list or null');
        }

        if (count($value) < self::MIN_OPTIONS || count($value) > self::MAX_OPTIONS) {
            throw new InvalidExamQuestions('a multiple-choice question needs between two and six options');
        }

        $options = array_map(
            fn (mixed $option): string => $this->plainText($option, self::MAX_OPTION_CHARACTERS, 'option'),
            $value,
        );

        if (count(array_unique($options)) !== count($options)) {
            throw new InvalidExamQuestions('options must be distinct');
        }

        return $options;
    }

    private function explanation(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidExamQuestions('the explanation must be a string');
        }

        $clean = BoundedText::freeText($value, self::MAX_EXPLANATION_CHARACTERS + 1);

        if ($clean === ''
            || mb_strlen($clean) > self::MAX_EXPLANATION_CHARACTERS
            || str_contains($clean, '<')
            || str_contains($clean, '>')) {
            throw new InvalidExamQuestions('the explanation must be bounded plain text');
        }

        return $clean;
    }

    private function plainText(mixed $value, int $max, string $field): string
    {
        if (! is_string($value)) {
            throw new InvalidExamQuestions("the {$field} must be a string");
        }

        try {
            return BoundedText::plainText($value, $max);
        } catch (InvalidArgumentException) {
            throw new InvalidExamQuestions("the {$field} must be bounded plain text");
        }
    }
}
