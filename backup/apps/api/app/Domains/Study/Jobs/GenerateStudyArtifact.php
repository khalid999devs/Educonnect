<?php

declare(strict_types=1);

namespace App\Domains\Study\Jobs;

use App\Domains\Intake\Enums\IntakeArtifactKind;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Study\AI\ExamQuestionSchemaV1;
use App\Domains\Study\AI\StudyGenerationPolicy;
use App\Domains\Study\AI\StudyGenerationRequest;
use App\Domains\Study\AI\StudyOutputSchemaV1;
use App\Domains\Study\Contracts\StudyGenerator;
use App\Domains\Study\Enums\StudyArtifactKind;
use App\Domains\Study\Enums\StudyArtifactStatus;
use App\Domains\Study\Models\StudyArtifact;
use App\Support\Ai\Exceptions\AiFeatureDisabled;
use App\Support\Ai\Exceptions\InvalidAiOutput;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates one study artifact.
 *
 * Structure is copied from ClassifyIntakeItem: `tries = 1` because retry is
 * domain-owned, a scalar-id-only constructor so no stale model is serialised,
 * collaborators method-injected into handle(), a locking claim() that makes
 * duplicate dispatch a no-op, a bounded output-retry loop where InvalidAiOutput
 * retries and any other Throwable abandons the provider, a persist() that
 * re-locks and re-checks before writing, and a failed() that frees the record
 * from its transient state.
 *
 * Two things are specific to study generation:
 *
 * 1. ShouldBeUnique keyed on "{knowledge item}:{kind}". Study generation is the
 *    obvious abuse vector in the product - a held-down button fans straight out
 *    to a paid provider - so identical queued jobs collapse into one.
 *
 * 2. HONEST FAILURE. There is no deterministic fallback. When the provider
 *    dies, the artifact becomes `failed` with a short human reason and NO
 *    payload, and the database enforces that: `study_artifacts_payload_status_consistent`
 *    permits a payload only on `ready`, and `study_artifacts_failure_reason_consistent`
 *    permits a reason only on `failed`. Fabricated study material cannot be
 *    represented in this schema, which is the point. A wrong exam answer is
 *    strictly worse than no exam answer.
 *
 * The transition to `ready` writes payload and status in ONE statement.
 * Splitting them trips SQLSTATE 23514 on whichever half lands first.
 */
final class GenerateStudyArtifact implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * The lock is held for the window in which a duplicate request is genuinely
     * a duplicate rather than a considered retry.
     */
    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $studyArtifactId,
        public readonly string $knowledgeItemPublicId,
        public readonly string $kind,
    ) {}

    /** Deduplicates identical queued generations, per doc-12 line 121. */
    public function uniqueId(): string
    {
        return "{$this->knowledgeItemPublicId}:{$this->kind}";
    }

    public function handle(StudyGenerator $generator, StudyGenerationPolicy $policy): void
    {
        $artifact = $this->claim();

        if (! $artifact instanceof StudyArtifact) {
            return;
        }

        $request = $this->buildRequest($artifact, $policy);

        if (! $request instanceof StudyGenerationRequest) {
            $this->markFailed($artifact, 'This material has no extracted text to study from yet.');

            return;
        }

        $startedAt = hrtime(true);
        $outcome = $this->attemptProvider($generator, $policy, $request);
        $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        if ($outcome === null) {
            $this->markFailed(
                $artifact,
                'The AI provider could not produce usable study material for this document. Nothing was invented in its place.',
            );

            return;
        }

        $this->persist($artifact, $generator, $request->kind, $outcome, $latencyMs);
    }

    /**
     * Frees the artifact from `queued`/`running` when the worker dies
     * terminally, so a lost worker cannot strand a student on a spinner.
     */
    public function failed(?Throwable $exception): void
    {
        $artifact = StudyArtifact::query()->whereKey($this->studyArtifactId)->first();

        if (! $artifact instanceof StudyArtifact || $artifact->status->isTerminal()) {
            return;
        }

        $this->markFailed($artifact, 'The study generation worker stopped before it finished. Try again.');
    }

    /**
     * Locks the artifact and refuses to run unless it is still queued. That
     * state guard is what makes duplicate dispatch idempotent even after the
     * ShouldBeUnique window has expired.
     */
    private function claim(): ?StudyArtifact
    {
        return DB::transaction(function (): ?StudyArtifact {
            $artifact = StudyArtifact::query()->whereKey($this->studyArtifactId)->lockForUpdate()->first();

            if (! $artifact instanceof StudyArtifact || $artifact->status !== StudyArtifactStatus::Queued) {
                return null;
            }

            $artifact->forceFill(['status' => StudyArtifactStatus::Running->value])->save();

            return $artifact;
        }, 3);
    }

    /**
     * Reads the document behind the artifact's knowledge item and bounds it to
     * `ai.features.<feature>.max_context_characters` - 24,000 - deliberately
     * NOT `intake.max_extracted_characters`, which is 200,000 and two orders of
     * magnitude too large for a prompt.
     */
    private function buildRequest(StudyArtifact $artifact, StudyGenerationPolicy $policy): ?StudyGenerationRequest
    {
        $item = KnowledgeItem::query()
            ->where('user_id', $artifact->user_id)
            ->whereKey($artifact->knowledge_item_id)
            ->first();

        if (! $item instanceof KnowledgeItem) {
            return null;
        }

        $intakeItemId = $item->getAttribute('intake_item_id');

        if (! is_numeric($intakeItemId)) {
            return null;
        }

        $kind = $artifact->kind;
        $limit = $policy->maxContextCharacters($kind);
        /* The ::int casts are load-bearing. PDO binds these placeholders as
           text, and substring(text, text, text) is the SQL-regex overload,
           which fails with SQLSTATE 22025. Casting pins the positional
           overload. Slicing in Postgres also keeps a 200,000 character
           artifact out of PHP memory. */
        $row = DB::table('intake_artifacts')
            ->where('intake_item_id', (int) $intakeItemId)
            ->where('kind', IntakeArtifactKind::ExtractedText->value)
            ->orderByDesc('id')
            ->selectRaw("COALESCE(SUBSTRING(text_content, 1, ?::int), '') AS window_text", [$limit])
            ->first();

        $text = $row === null ? '' : (string) $row->window_text;

        if (trim($text) === '') {
            return null;
        }

        return new StudyGenerationRequest(
            kind: $kind,
            title: (string) $item->getAttribute('title'),
            extractedText: $text,
            sourceUrl: is_string($item->getAttribute('source_url')) ? (string) $item->getAttribute('source_url') : null,
            maxQuestions: $policy->maxQuestions(),
        );
    }

    /**
     * Bounded output retries. An InvalidAiOutput means the provider answered
     * and answered badly, which is worth one more attempt. Any other Throwable
     * - including a disabled kill switch or an open circuit breaker - means the
     * provider is unavailable, so it is abandoned immediately. There is no
     * second provider to fall through to, and that is deliberate.
     *
     * @return array<string, mixed>|null
     */
    private function attemptProvider(
        StudyGenerator $generator,
        StudyGenerationPolicy $policy,
        StudyGenerationRequest $request,
    ): ?array {
        $attempts = $policy->maxOutputRetries($request->kind) + 1;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            try {
                return $generator->generate($request);
            } catch (InvalidAiOutput) {
                Log::info('Study generation output rejected.', [
                    'provider' => $generator->name(),
                    'kind' => $request->kind->value,
                    'attempt' => $attempt + 1,
                    'study_artifact_id' => $this->studyArtifactId,
                ]);
            } catch (AiFeatureDisabled) {
                Log::info('Study generation is switched off for this capability.', [
                    'kind' => $request->kind->value,
                    'study_artifact_id' => $this->studyArtifactId,
                ]);

                return null;
            } catch (Throwable $exception) {
                Log::warning('Study generation provider failed.', [
                    'provider' => $generator->name(),
                    'kind' => $request->kind->value,
                    'exception_type' => $exception::class,
                    'study_artifact_id' => $this->studyArtifactId,
                ]);

                return null;
            }
        }

        return null;
    }

    /**
     * Re-locks, re-checks the state, and stamps provenance. Payload and status
     * move together in one UPDATE; the CHECK constraint rejects any attempt to
     * write them separately.
     *
     * @param  array<string, mixed>  $payload
     */
    private function persist(
        StudyArtifact $artifact,
        StudyGenerator $generator,
        StudyArtifactKind $kind,
        array $payload,
        int $latencyMs,
    ): void {
        DB::transaction(function () use ($artifact, $generator, $kind, $payload, $latencyMs): void {
            $fresh = StudyArtifact::query()->whereKey($artifact->getKey())->lockForUpdate()->first();

            if (! $fresh instanceof StudyArtifact || $fresh->status !== StudyArtifactStatus::Running) {
                return;
            }

            $fresh->forceFill([
                'status' => StudyArtifactStatus::Ready->value,
                'payload' => $payload,
                'failure_reason' => null,
                'provider' => $generator->name(),
                'model' => $generator->model($kind),
                'schema_version' => $this->schemaVersion($kind),
                'latency_ms' => max(0, $latencyMs),
            ])->save();
        }, 3);
    }

    /**
     * The honest-failure path. No payload is ever written here, so a failed
     * generation is visibly empty rather than quietly wrong.
     */
    private function markFailed(StudyArtifact $artifact, string $reason): void
    {
        DB::transaction(function () use ($artifact, $reason): void {
            $fresh = StudyArtifact::query()->whereKey($artifact->getKey())->lockForUpdate()->first();

            if (! $fresh instanceof StudyArtifact || $fresh->status->isTerminal()) {
                return;
            }

            $fresh->forceFill([
                'status' => StudyArtifactStatus::Failed->value,
                'payload' => null,
                'failure_reason' => mb_substr($reason, 0, 255),
            ])->save();
        }, 3);
    }

    private function schemaVersion(StudyArtifactKind $kind): string
    {
        return $kind->isExamQuestions()
            ? ExamQuestionSchemaV1::VERSION
            : StudyOutputSchemaV1::VERSION;
    }
}
