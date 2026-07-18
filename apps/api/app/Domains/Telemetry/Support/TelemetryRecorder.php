<?php

declare(strict_types=1);

namespace App\Domains\Telemetry\Support;

use App\Domains\Telemetry\Enums\TelemetryOutcome;
use App\Domains\Telemetry\Enums\TelemetryType;
use App\Domains\Telemetry\Models\TelemetryEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Best-effort writer for durable operational telemetry. Recording is never
 * allowed to break the request or job that emits it: every write is guarded,
 * disabled cleanly by config, and carries only bounded, redacted metadata
 * (never prompts, completions, private content, or personal identifiers).
 */
final class TelemetryRecorder
{
    private const NAME_MAX = 120;

    private const METADATA_STRING_MAX = 500;

    public function enabled(): bool
    {
        return (bool) config('telemetry.enabled', true);
    }

    /**
     * Record one outbound AI-provider call (intake classification, Copilot).
     *
     * @param  array<string, scalar|null>  $metadata
     */
    public function recordAiCall(
        string $feature,
        TelemetryOutcome $outcome,
        ?int $durationMs = null,
        array $metadata = [],
    ): void {
        $this->record(TelemetryType::AiCall, $feature, $outcome, null, $durationMs, $metadata);
    }

    /**
     * Record the health of a background intake job run.
     *
     * @param  array<string, scalar|null>  $metadata
     */
    public function recordIntakeJob(
        string $job,
        TelemetryOutcome $outcome,
        ?int $durationMs = null,
        array $metadata = [],
    ): void {
        $this->record(TelemetryType::IntakeJob, $job, $outcome, null, $durationMs, $metadata);
    }

    /**
     * Capture a redacted server-fault (5xx) for the error-telemetry view.
     *
     * @param  array<string, scalar|null>  $metadata
     */
    public function recordServerError(int $statusCode, string $code, array $metadata = []): void
    {
        $this->record(TelemetryType::Error, $code, TelemetryOutcome::Failure, $statusCode, null, $metadata);
    }

    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function record(
        TelemetryType $type,
        string $name,
        TelemetryOutcome $outcome,
        ?int $statusCode = null,
        ?int $durationMs = null,
        array $metadata = [],
    ): void {
        if (! $this->enabled()) {
            return;
        }

        try {
            $attributes = [
                'type' => $type->value,
                'name' => $this->sanitizeName($name),
                'outcome' => $outcome->value,
                'status_code' => $statusCode,
                'duration_ms' => $durationMs !== null ? max(0, $durationMs) : null,
                'occurred_at' => now(),
            ];

            // Omit empty metadata so the column's `{}`::jsonb default (a JSON
            // object) applies; an empty PHP array would encode as `[]` and trip
            // the object CHECK constraint.
            $clean = $this->sanitizeMetadata($metadata);

            if ($clean !== []) {
                $attributes['metadata'] = $clean;
            }

            TelemetryEvent::forceCreate($attributes);
        } catch (Throwable $exception) {
            // Telemetry must never surface to the caller; note it and move on.
            Log::debug('telemetry.record_failed', [
                'type' => $type->value,
                'exception' => $exception::class,
            ]);
        }
    }

    private function sanitizeName(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = (string) preg_replace('/[^a-z0-9._-]+/', '.', $normalized);
        $normalized = trim($normalized, '._-');

        if ($normalized === '' || ! ctype_alpha($normalized[0])) {
            $normalized = 'unknown'.($normalized === '' ? '' : '.'.$normalized);
        }

        return mb_substr($normalized, 0, self::NAME_MAX);
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     * @return array<string, scalar|null>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key) || ! (is_scalar($value) || $value === null)) {
                continue;
            }

            $clean[mb_substr($key, 0, 64)] = is_string($value)
                ? mb_substr($value, 0, self::METADATA_STRING_MAX)
                : $value;
        }

        $maxBytes = (int) config('telemetry.max_metadata_bytes', 4096);

        if (strlen((string) json_encode($clean)) > $maxBytes) {
            return [];
        }

        return $clean;
    }
}
