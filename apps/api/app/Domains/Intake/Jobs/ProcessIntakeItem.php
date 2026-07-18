<?php

declare(strict_types=1);

namespace App\Domains\Intake\Jobs;

use App\Domains\Intake\Contracts\IntakeContentExtractor;
use App\Domains\Intake\Enums\IntakeArtifactKind;
use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Enums\IntakeSourceType;
use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;
use App\Domains\Intake\Models\IntakeArtifact;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Support\IntakeEventRecorder;
use App\Domains\Intake\Support\LinkContentFetcher;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Resources\Support\ResourceStorage;
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

final class ProcessIntakeItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $intakeItemId) {}

    public function handle(
        LinkContentFetcher $fetcher,
        IntakeContentExtractor $extractor,
        IntakeEventRecorder $events,
        ResourceStorage $storage,
        TelemetryRecorder $telemetry,
    ): void {
        $item = $this->claim($events, $telemetry);

        if (! $item instanceof IntakeItem) {
            return;
        }

        try {
            [$rawContent, $contentType, $byteSize, $acquisitionMetadata] = $item->source_type === IntakeSourceType::Link
                ? $this->acquireLink($item, $fetcher)
                : $this->acquireFile($item, $storage);

            $this->storeArtifact($item, IntakeArtifactKind::AcquiredContent, $contentType, $byteSize, [
                'text' => $item->source_type === IntakeSourceType::Link ? $rawContent : null,
                'metadata' => $acquisitionMetadata,
            ]);

            $text = $extractor->extract($rawContent, $contentType);

            $this->storeArtifact($item, IntakeArtifactKind::ExtractedText, 'text/plain', strlen($text), [
                'text' => $text,
                'metadata' => ['characters' => mb_strlen($text)],
            ]);

            $this->finish($item, $events, mb_strlen($text));
            $telemetry->recordIntakeJob('intake.process', TelemetryOutcome::Success);
        } catch (IntakeAcquisitionFailure $failure) {
            $this->markFailed($item, $events, $failure->failureCode, $failure->getMessage());
            $telemetry->recordIntakeJob('intake.process', TelemetryOutcome::Failure, null, [
                'code' => $failure->failureCode->value,
            ]);
        } catch (Throwable $exception) {
            Log::error('Intake processing failed unexpectedly.', [
                'intake_item_id' => $this->intakeItemId,
                'exception_type' => $exception::class,
            ]);
            $this->markFailed($item, $events, IntakeFailureCode::ExtractionFailed, 'unexpected processing failure');
            $telemetry->recordIntakeJob('intake.process', TelemetryOutcome::Failure, null, [
                'code' => IntakeFailureCode::ExtractionFailed->value,
            ]);
        }
    }

    /**
     * Called by the queue when the job dies terminally (an escaped exception, a
     * timeout, or a graceful worker shutdown). A hard kill that never invokes
     * this is the reaper's job; here we free the item from `extracting` so it is
     * not stranded forever.
     */
    public function failed(?Throwable $exception): void
    {
        $events = app(IntakeEventRecorder::class);
        $telemetry = app(TelemetryRecorder::class);
        $item = IntakeItem::query()->whereKey($this->intakeItemId)->first();

        if (! $item instanceof IntakeItem || $item->state !== IntakeState::Extracting) {
            return;
        }

        $this->markFailed($item, $events, IntakeFailureCode::Interrupted, 'processing worker terminated before completion');
        $telemetry->recordIntakeJob('intake.process', TelemetryOutcome::Failure, null, ['code' => 'interrupted']);
    }

    private function claim(IntakeEventRecorder $events, TelemetryRecorder $telemetry): ?IntakeItem
    {
        return DB::transaction(function () use ($events, $telemetry): ?IntakeItem {
            $item = IntakeItem::query()->whereKey($this->intakeItemId)->lockForUpdate()->first();

            if (! $item instanceof IntakeItem || $item->state !== IntakeState::Queued) {
                return null;
            }

            if ($item->attempts >= (int) config('intake.max_attempts')) {
                $item->forceFill([
                    'state' => IntakeState::Extracting->value,
                ])->save();
                $item->forceFill([
                    'state' => IntakeState::FailedFinal->value,
                    'failure_code' => IntakeFailureCode::AttemptsExhausted->value,
                    'finished_at' => now(),
                ])->save();
                $events->record($item, 'failed', IntakeState::Queued, IntakeState::FailedFinal, 'attempts exhausted');
                $telemetry->recordIntakeJob('intake.process', TelemetryOutcome::Failure, null, [
                    'code' => IntakeFailureCode::AttemptsExhausted->value,
                ]);

                return null;
            }

            // A fresh attempt replaces any artifacts from a prior failed run.
            $item->artifacts()->delete();
            $item->forceFill([
                'state' => IntakeState::Extracting->value,
                'attempts' => $item->attempts + 1,
                'started_at' => now(),
            ])->save();
            $events->record($item, 'processing_started', IntakeState::Queued, IntakeState::Extracting, 'attempt '.$item->attempts);

            return $item;
        }, 3);
    }

    /** @return array{0: string, 1: string, 2: int, 3: array<string, mixed>} */
    private function acquireLink(IntakeItem $item, LinkContentFetcher $fetcher): array
    {
        $content = $fetcher->fetch((string) $item->url);

        return [
            $content->body,
            $content->contentType,
            $content->byteSize,
            ['final_url' => $content->finalUrl, 'fetched_at' => now()->toISOString()],
        ];
    }

    /** @return array{0: string, 1: string, 2: int, 3: array<string, mixed>} */
    private function acquireFile(IntakeItem $item, ResourceStorage $storage): array
    {
        $storedFile = $item->resource?->storedFile;

        if (! $storedFile instanceof StoredFile) {
            throw new IntakeAcquisitionFailure(IntakeFailureCode::FileUnavailable, 'the stored file is missing');
        }

        $maxBytes = (int) config('intake.max_fetch_bytes');

        try {
            $stream = $storage->readStream((string) $storedFile->object_key);
            $body = '';

            while (! feof($stream) && strlen($body) <= $maxBytes) {
                $chunk = fread($stream, 65536);

                if ($chunk === false) {
                    break;
                }

                $body .= $chunk;
            }

            fclose($stream);
        } catch (Throwable) {
            throw new IntakeAcquisitionFailure(IntakeFailureCode::FileUnavailable, 'the stored file could not be read');
        }

        if (strlen($body) > $maxBytes) {
            throw new IntakeAcquisitionFailure(IntakeFailureCode::ContentTooLarge, 'the stored file exceeds the intake size limit');
        }

        $contentType = (string) ($storedFile->verified_mime_type ?? $storedFile->declared_mime_type);

        return [
            $body,
            $contentType,
            strlen($body),
            ['object_recorded' => true, 'read_at' => now()->toISOString()],
        ];
    }

    /** @param array{text: string|null, metadata: array<string, mixed>} $payload */
    private function storeArtifact(
        IntakeItem $item,
        IntakeArtifactKind $kind,
        string $contentType,
        int $byteSize,
        array $payload,
    ): void {
        $artifact = new IntakeArtifact;
        $artifact->forceFill([
            'intake_item_id' => $item->getKey(),
            'kind' => $kind->value,
            'content_type' => $contentType,
            'byte_size' => $byteSize,
            'text_content' => $payload['text'] !== null && $payload['text'] !== ''
                ? mb_substr($payload['text'], 0, (int) config('intake.max_extracted_characters'))
                : null,
            'metadata' => $payload['metadata'],
        ])->save();
    }

    private function finish(IntakeItem $item, IntakeEventRecorder $events, int $characters): void
    {
        DB::transaction(function () use ($item, $events, $characters): void {
            $fresh = IntakeItem::query()->whereKey($item->getKey())->lockForUpdate()->first();

            if (! $fresh instanceof IntakeItem || $fresh->state !== IntakeState::Extracting) {
                return;
            }

            $fresh->forceFill([
                'state' => IntakeState::Extracted->value,
                'finished_at' => now(),
            ])->save();
            $events->record($fresh, 'extracted', IntakeState::Extracting, IntakeState::Extracted, $characters.' characters extracted');

            ClassifyIntakeItem::dispatch((int) $fresh->getKey())->afterCommit();
        }, 3);
    }

    private function markFailed(
        ?IntakeItem $item,
        IntakeEventRecorder $events,
        IntakeFailureCode $code,
        string $detail,
    ): void {
        if (! $item instanceof IntakeItem) {
            return;
        }

        DB::transaction(function () use ($item, $events, $code, $detail): void {
            $fresh = IntakeItem::query()->whereKey($item->getKey())->lockForUpdate()->first();

            if (! $fresh instanceof IntakeItem || $fresh->state !== IntakeState::Extracting) {
                return;
            }

            $retryable = $code->isRetryable() && $fresh->attempts < (int) config('intake.max_attempts');
            $fresh->forceFill([
                'state' => $retryable ? IntakeState::FailedRetryable->value : IntakeState::FailedFinal->value,
                'failure_code' => $code->value,
                'finished_at' => now(),
            ])->save();
            $events->record(
                $fresh,
                'failed',
                IntakeState::Extracting,
                $retryable ? IntakeState::FailedRetryable : IntakeState::FailedFinal,
                $detail,
            );
        }, 3);
    }
}
