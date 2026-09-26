<?php

declare(strict_types=1);

namespace App\Domains\Study\AI;

use App\Domains\Study\Contracts\StudyGenerator;
use App\Domains\Study\Enums\StudyArtifactKind;
use App\Domains\Study\Queries\BuildStudyGenerationMessages;
use App\Support\Ai\AiAgentRunner;
use App\Support\Ai\OpenAiClient;
use JsonException;
use RuntimeException;

/**
 * OpenAI-backed study generation, executed inside the shared AiAgentRunner
 * envelope under the AiFeature the requested kind names - StudySummary,
 * TopicExplanation, QuickLearn, or ExamQuestions - so each capability has its
 * own model policy, kill switch, circuit breaker, and telemetry stream.
 *
 * The fallback passed to the runner is deliberately null, and that is the
 * product requirement rather than an omission: when the provider dies the
 * runner rethrows, the job records the artifact as `failed` with an honest
 * reason, and no study material is invented. A wrong exam answer is strictly
 * worse than no exam answer.
 *
 * The agent owns transport and bounded output retries and nothing else: the
 * prompt lives in BuildStudyGenerationMessages and validation in
 * StudyOutputSchemaV1 / ExamQuestionSchemaV1.
 */
final readonly class StudyGenerationAgent implements StudyGenerator
{
    public function __construct(
        private OpenAiClient $client,
        private AiAgentRunner $runner,
        private BuildStudyGenerationMessages $messages,
        private StudyOutputSchemaV1 $studySchema,
        private ExamQuestionSchemaV1 $examSchema,
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function model(StudyArtifactKind $kind): string
    {
        return $kind->feature()->model();
    }

    /** @return array<string, mixed> */
    public function generate(StudyGenerationRequest $request): array
    {
        $result = $this->runner->run(
            $request->kind->feature(),
            $this->name(),
            fn (): array => $this->attempt($request),
        );

        /** @var array<string, mixed> $payload */
        $payload = $result->value;

        return $payload;
    }

    /**
     * Exactly one provider call and one schema pass. The bounded output-retry
     * loop lives in GenerateStudyArtifact, mirroring ClassifyIntakeItem: an
     * InvalidAiOutput raised here reaches the job, which retries; anything else
     * reaches the job, which abandons the provider. Retrying here as well would
     * square the attempt count and hide half the calls from telemetry.
     *
     * @return array<string, mixed>
     */
    private function attempt(StudyGenerationRequest $request): array
    {
        $decoded = $this->completion($request->kind, $this->messages->execute($request));

        return $request->kind->isExamQuestions()
            ? $this->examSchema->validate($decoded, $request->maxQuestions)
            : $this->studySchema->validate($decoded);
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    private function completion(StudyArtifactKind $kind, array $messages): array
    {
        $content = $this->client->chat(
            $this->model($kind),
            $messages,
            jsonObject: true,
            feature: $kind->feature(),
        );

        try {
            $decoded = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('OpenAI returned invalid JSON.');
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned a non-object payload.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
