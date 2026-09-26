<?php

declare(strict_types=1);

namespace App\Domains\Study\Enums;

use App\Support\Ai\AiFeature;

/**
 * The four ready-made study actions the Study section and the Second Brain
 * workspace offer. The values mirror the `study_artifacts_kind_known` CHECK in
 * `..._000019` exactly; adding a case here without widening that CHECK produces
 * SQLSTATE 23514 at write time.
 *
 * Each kind names its own AiFeature, so model policy, kill switch, circuit
 * breaker, and telemetry are all per-kind. A flood of failing exam-question
 * generations cannot short-circuit summaries.
 */
enum StudyArtifactKind: string
{
    case Summary = 'summary';
    case TopicExplanation = 'topic_explanation';
    case QuickLearn = 'quick_learn';
    case ExamQuestions = 'exam_questions';

    public function feature(): AiFeature
    {
        return match ($this) {
            self::Summary => AiFeature::StudySummary,
            self::TopicExplanation => AiFeature::TopicExplanation,
            self::QuickLearn => AiFeature::QuickLearn,
            self::ExamQuestions => AiFeature::ExamQuestions,
        };
    }

    /** Exam questions carry their own schema; the other three share one. */
    public function isExamQuestions(): bool
    {
        return $this === self::ExamQuestions;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }
}
