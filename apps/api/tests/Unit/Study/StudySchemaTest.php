<?php

declare(strict_types=1);

namespace Tests\Unit\Study;

use App\Domains\Study\AI\ExamQuestionSchemaV1;
use App\Domains\Study\AI\StudyOutputSchemaV1;
use App\Domains\Study\Exceptions\InvalidExamQuestions;
use App\Domains\Study\Exceptions\InvalidStudyOutput;
use App\Support\Ai\Exceptions\InvalidAiOutput;
use PHPUnit\Framework\TestCase;

/**
 * The schemas are the boundary between a provider that read an untrusted
 * document and the database. Everything a prompt-injected document could
 * persuade a model to emit has to die here.
 */
final class StudySchemaTest extends TestCase
{
    public function test_a_well_formed_study_payload_round_trips(): void
    {
        $validated = (new StudyOutputSchemaV1)->validate([
            'title' => 'Photosynthesis',
            'overview' => "It covers two stages.\nBoth are examinable.",
            'sections' => [['heading' => 'Light reactions', 'body' => "Electrons move.\nATP forms."]],
            'key_points' => ['Carbon fixation follows the light reactions.'],
        ]);

        self::assertSame('Photosynthesis', $validated['title']);
        self::assertCount(1, $validated['sections']);
        self::assertSame(['Carbon fixation follows the light reactions.'], $validated['key_points']);
    }

    public function test_study_rejections_are_retryable_ai_output_failures(): void
    {
        $schema = new StudyOutputSchemaV1;

        self::assertInstanceOf(InvalidAiOutput::class, new InvalidStudyOutput('x'));

        $rejected = [
            'unknown top level key' => ['title' => 'a', 'overview' => 'b', 'sections' => [], 'evil' => 'x'],
            'markup in a heading' => [
                'title' => 'a',
                'overview' => 'b',
                'sections' => [['heading' => '<script>alert(1)</script>', 'body' => 'c']],
            ],
            'markup in a body' => [
                'title' => 'a',
                'overview' => 'b',
                'sections' => [['heading' => 'h', 'body' => 'c <img onerror=x>']],
            ],
            'unknown section key' => [
                'title' => 'a',
                'overview' => 'b',
                'sections' => [['heading' => 'h', 'body' => 'c', 'script' => 'x']],
            ],
            'empty sections' => ['title' => 'a', 'overview' => 'b', 'sections' => []],
            'too many sections' => [
                'title' => 'a',
                'overview' => 'b',
                'sections' => array_fill(0, StudyOutputSchemaV1::MAX_SECTIONS + 1, ['heading' => 'h', 'body' => 'b']),
            ],
            'too many key points' => [
                'title' => 'a',
                'overview' => 'b',
                'sections' => [['heading' => 'h', 'body' => 'b']],
                'key_points' => array_fill(0, StudyOutputSchemaV1::MAX_KEY_POINTS + 1, 'point'),
            ],
        ];

        foreach ($rejected as $label => $payload) {
            try {
                $schema->validate($payload);
                self::fail("Expected [{$label}] to be rejected.");
            } catch (InvalidStudyOutput) {
                self::assertTrue(true);
            }
        }
    }

    public function test_a_well_formed_exam_set_round_trips_and_keeps_open_questions(): void
    {
        $validated = (new ExamQuestionSchemaV1)->validate([
            'questions' => [
                [
                    'prompt' => 'Name the carbon-fixing cycle.',
                    'options' => null,
                    'answer' => 'The Calvin cycle',
                    'explanation' => 'Stated in section two.',
                ],
            ],
        ], 20);

        self::assertNull($validated['questions'][0]['options']);
        self::assertSame('The Calvin cycle', $validated['questions'][0]['answer']);
    }

    /**
     * The single most important rejection in the domain: a multiple-choice
     * question whose stated answer is not one of its own options is a
     * contradiction, and shipping it would be worse than shipping nothing.
     */
    public function test_an_answer_outside_its_own_options_is_rejected(): void
    {
        $this->expectException(InvalidExamQuestions::class);

        (new ExamQuestionSchemaV1)->validate([
            'questions' => [
                [
                    'prompt' => 'Name the carbon-fixing cycle.',
                    'options' => ['The Krebs cycle', 'Glycolysis'],
                    'answer' => 'The Calvin cycle',
                    'explanation' => 'Stated in section two.',
                ],
            ],
        ], 20);
    }

    public function test_exam_sets_reject_over_count_duplicate_options_and_markup(): void
    {
        $schema = new ExamQuestionSchemaV1;
        $question = [
            'prompt' => 'p',
            'options' => null,
            'answer' => 'a',
            'explanation' => 'e',
        ];

        $rejected = [
            'over the requested count' => ['questions' => array_fill(0, 4, $question)],
            'empty question list' => ['questions' => []],
            'unknown top level key' => ['questions' => [$question], 'notes' => 'x'],
            'unknown question key' => ['questions' => [$question + ['hint' => 'x']]],
            'a single option' => ['questions' => [[...$question, 'options' => ['only'], 'answer' => 'only']]],
            'duplicate options' => ['questions' => [[...$question, 'options' => ['a', 'a'], 'answer' => 'a']]],
            'markup in a prompt' => ['questions' => [[...$question, 'prompt' => '<b>p</b>']]],
        ];

        foreach ($rejected as $label => $payload) {
            try {
                $schema->validate($payload, 3);
                self::fail("Expected [{$label}] to be rejected.");
            } catch (InvalidExamQuestions) {
                self::assertTrue(true);
            }
        }
    }
}
