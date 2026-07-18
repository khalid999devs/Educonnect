<?php

declare(strict_types=1);

namespace App\Domains\Intake\Jobs;

use App\Domains\Courses\Models\Course;
use App\Domains\Intake\AI\ClassificationPolicy;
use App\Domains\Intake\AI\ClassificationRequest;
use App\Domains\Intake\AI\RuleBasedClassificationProvider;
use App\Domains\Intake\AI\SuggestionSchemaV1;
use App\Domains\Intake\Contracts\AIProvider;
use App\Domains\Intake\Enums\IntakeArtifactKind;
use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Enums\IntakeSuggestionStatus;
use App\Domains\Intake\Exceptions\InvalidSuggestionOutput;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Models\IntakeSuggestion;
use App\Domains\Intake\Support\IntakeEventRecorder;
use App\Domains\Telemetry\Enums\TelemetryOutcome;
use App\Domains\Telemetry\Support\TelemetryRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ClassifyIntakeItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $intakeItemId) {}

    public function handle(
        ClassificationPolicy $policy,
        SuggestionSchemaV1 $schema,
        IntakeEventRecorder $events,
        TelemetryRecorder $telemetry,
    ): void {
        $item = $this->claim($events);

        if (! $item instanceof IntakeItem) {
            return;
        }

        $request = $this->buildRequest($item, $policy);

        if (! $request instanceof ClassificationRequest) {
            $this->markFailed($item, $events, 'no extracted text artifact is available');
            $telemetry->recordIntakeJob('intake.classify', TelemetryOutcome::Failure, null, ['code' => 'no_text']);

            return;
        }

        $usedFallback = false;

        foreach ($policy->providers() as $provider) {
            $remote = ! $provider instanceof RuleBasedClassificationProvider;
            $startedAt = hrtime(true);
            $outcome = $this->attemptProvider($provider, $policy, $schema, $request);
            $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            if ($outcome !== null) {
                if ($remote) {
                    $telemetry->recordAiCall('intake.classification', TelemetryOutcome::Success, $outcome['latency_ms'], [
                        'provider' => $provider->name(),
                        'model' => $provider->model(),
                    ]);
                } elseif ($usedFallback) {
                    // A remote provider failed first; the deterministic path answered.
                    $telemetry->recordAiCall('intake.classification', TelemetryOutcome::Fallback, $latencyMs, [
                        'provider' => $provider->name(),
                    ]);
                }

                $this->persist($item, $events, $provider, $outcome['suggestions'], $outcome['latency_ms']);
                $telemetry->recordIntakeJob('intake.classify', TelemetryOutcome::Success);

                return;
            }

            if ($remote) {
                $usedFallback = true;
                $telemetry->recordAiCall('intake.classification', TelemetryOutcome::Failure, $latencyMs, [
                    'provider' => $provider->name(),
                ]);
            }

            $events->record($item, 'classifier_rejected', null, null, 'provider '.$provider->name().' produced no valid output');
        }

        $this->markFailed($item, $events, 'every approved provider failed schema validation');
        $telemetry->recordIntakeJob('intake.classify', TelemetryOutcome::Failure, null, [
            'code' => 'classification_failed',
        ]);
    }

    /**
     * Frees the item from `organizing` when the classification job dies
     * terminally, so a lost worker cannot strand it. A hard kill that never
     * calls this is handled by the reaper.
     */
    public function failed(?Throwable $exception): void
    {
        $events = app(IntakeEventRecorder::class);
        $telemetry = app(TelemetryRecorder::class);
        $item = IntakeItem::query()->whereKey($this->intakeItemId)->first();

        if (! $item instanceof IntakeItem || $item->state !== IntakeState::Organizing) {
            return;
        }

        $this->markFailed($item, $events, 'classification worker terminated before completion');
        $telemetry->recordIntakeJob('intake.classify', TelemetryOutcome::Failure, null, ['code' => 'interrupted']);
    }

    private function claim(IntakeEventRecorder $events): ?IntakeItem
    {
        return DB::transaction(function () use ($events): ?IntakeItem {
            $item = IntakeItem::query()->whereKey($this->intakeItemId)->lockForUpdate()->first();

            if (! $item instanceof IntakeItem || $item->state !== IntakeState::Extracted) {
                return null;
            }

            // A fresh classification run replaces prior undecided proposals.
            $item->suggestions()
                ->where('status', IntakeSuggestionStatus::Proposed->value)
                ->delete();
            $item->forceFill(['state' => IntakeState::Organizing->value])->save();
            $events->record($item, 'organizing_started', IntakeState::Extracted, IntakeState::Organizing);

            return $item;
        }, 3);
    }

    private function buildRequest(IntakeItem $item, ClassificationPolicy $policy): ?ClassificationRequest
    {
        $artifact = $item->artifacts()
            ->where('kind', IntakeArtifactKind::ExtractedText->value)
            ->first();
        $text = $artifact?->text_content;

        if (! is_string($text) || trim($text) === '') {
            return null;
        }

        $courses = Course::query()
            ->where('user_id', $item->user_id)
            ->whereNull('archived_at')
            ->orderBy('id')
            ->limit($policy->maxCourses())
            ->get(['public_id', 'title', 'code'])
            ->map(static fn (Course $course): array => [
                'public_id' => (string) $course->public_id,
                'title' => (string) $course->title,
                'code' => $course->code,
            ])
            ->all();

        return new ClassificationRequest(
            extractedText: $text,
            context: $item->context,
            sourceUrl: $item->url,
            courses: $courses,
            maxSuggestions: $policy->maxSuggestions(),
        );
    }

    /** @return array{suggestions: list<array<string, mixed>>, latency_ms: int}|null */
    private function attemptProvider(
        AIProvider $provider,
        ClassificationPolicy $policy,
        SuggestionSchemaV1 $schema,
        ClassificationRequest $request,
    ): ?array {
        $attempts = $policy->maxOutputRetries() + 1;
        $startedAt = hrtime(true);

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            try {
                $raw = $provider->classify($request);
                $validated = $schema->validate($raw, $request);

                return [
                    'suggestions' => $validated,
                    'latency_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
                ];
            } catch (InvalidSuggestionOutput) {
                Log::info('Intake classifier output rejected.', [
                    'provider' => $provider->name(),
                    'attempt' => $attempt + 1,
                    'intake_item_id' => $this->intakeItemId,
                ]);
            } catch (Throwable $exception) {
                Log::warning('Intake classifier provider failed.', [
                    'provider' => $provider->name(),
                    'exception_type' => $exception::class,
                    'intake_item_id' => $this->intakeItemId,
                ]);

                return null;
            }
        }

        return null;
    }

    /** @param list<array<string, mixed>> $suggestions */
    private function persist(
        IntakeItem $item,
        IntakeEventRecorder $events,
        AIProvider $provider,
        array $suggestions,
        int $latencyMs,
    ): void {
        DB::transaction(function () use ($item, $events, $provider, $suggestions, $latencyMs): void {
            $fresh = IntakeItem::query()->whereKey($item->getKey())->lockForUpdate()->first();

            if (! $fresh instanceof IntakeItem || $fresh->state !== IntakeState::Organizing) {
                return;
            }

            foreach ($suggestions as $suggestion) {
                $record = new IntakeSuggestion;
                $record->forceFill([
                    'intake_item_id' => $fresh->getKey(),
                    'kind' => $suggestion['kind'],
                    'payload' => [
                        'title' => $suggestion['title'],
                        'description' => $suggestion['description'],
                        'due_at' => $suggestion['due_at'],
                        'course_public_id' => $suggestion['course_public_id'],
                        'url' => $suggestion['url'],
                    ],
                    'schema_version' => SuggestionSchemaV1::VERSION,
                    'confidence' => number_format((float) $suggestion['confidence'], 3, '.', ''),
                    'reason' => $suggestion['reason'],
                    'status' => IntakeSuggestionStatus::Proposed->value,
                ])->save();
            }

            $fresh->forceFill([
                'state' => IntakeState::AwaitingReview->value,
                'classification_provider' => $provider->name(),
                'classification_model' => $provider->model(),
                'classification_schema_version' => SuggestionSchemaV1::VERSION,
                'classification_latency_ms' => $latencyMs,
            ])->save();
            $events->record(
                $fresh,
                'suggestions_ready',
                IntakeState::Organizing,
                IntakeState::AwaitingReview,
                count($suggestions).' suggestions from '.$provider->name(),
            );
        }, 3);
    }

    private function markFailed(IntakeItem $item, IntakeEventRecorder $events, string $detail): void
    {
        DB::transaction(function () use ($item, $events, $detail): void {
            $fresh = IntakeItem::query()->whereKey($item->getKey())->lockForUpdate()->first();

            if (! $fresh instanceof IntakeItem || $fresh->state !== IntakeState::Organizing) {
                return;
            }

            $retryable = $fresh->attempts < (int) config('intake.max_attempts');
            $fresh->forceFill([
                'state' => $retryable ? IntakeState::FailedRetryable->value : IntakeState::FailedFinal->value,
                'failure_code' => IntakeFailureCode::ClassificationFailed->value,
            ])->save();
            $events->record(
                $fresh,
                'failed',
                IntakeState::Organizing,
                $retryable ? IntakeState::FailedRetryable : IntakeState::FailedFinal,
                $detail,
            );
        }, 3);
    }
}
