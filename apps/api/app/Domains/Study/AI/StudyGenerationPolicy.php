<?php

declare(strict_types=1);

namespace App\Domains\Study\AI;

use App\Domains\Study\Enums\StudyArtifactKind;
use App\Support\Ai\AiFeature;

/**
 * The per-kind bounds for study generation, read from `ai.features.*` so a
 * change is a config edit rather than a deploy.
 *
 * There is deliberately no provider chain here, unlike ClassificationPolicy.
 * Study generation has no deterministic fallback: a fabricated summary or a
 * wrong exam answer is strictly worse than no answer, so a failing provider
 * produces a `failed` artifact with an honest reason instead of invented
 * material.
 */
final readonly class StudyGenerationPolicy
{
    public function maxContextCharacters(StudyArtifactKind $kind): int
    {
        return $kind->feature()->limit('max_context_characters', 24_000);
    }

    public function maxQuestions(): int
    {
        return AiFeature::ExamQuestions->limit('max_questions', 20);
    }

    /**
     * Bounded output retries. A schema rejection means the provider answered
     * badly and is worth one more attempt; anything else abandons it.
     */
    public function maxOutputRetries(StudyArtifactKind $kind): int
    {
        return max(0, (int) config("ai.features.{$kind->feature()->value}.max_output_retries", 1));
    }
}
