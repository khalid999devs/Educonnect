<?php

declare(strict_types=1);

namespace App\Domains\Telemetry\Queries;

use App\Domains\Telemetry\Enums\TelemetryType;
use App\Domains\Telemetry\Support\HttpMetricsStore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The privacy-safe operational-telemetry overview for administrators (ADM-004):
 * AI-provider health, background-job health, captured server errors, and HTTP
 * latency — aggregate facts only, never prompts, completions, or private
 * content. This is the surface the metrics substrate was built to enable.
 */
final class BuildTelemetryOverview
{
    public function __construct(private readonly HttpMetricsStore $http) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $windowHours = max(1, (int) config('telemetry.overview_window_hours', 24));
        $since = CarbonImmutable::now('UTC')->subHours($windowHours);

        return [
            'window_hours' => $windowHours,
            'generated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'ai' => $this->ai($since),
            'jobs' => $this->jobs($since),
            'errors' => $this->errors($since),
            'http' => $this->http->snapshot(),
        ];
    }

    /** @return array<string, mixed> */
    private function ai(CarbonImmutable $since): array
    {
        $outcomes = $this->outcomeCounts(TelemetryType::AiCall, $since);
        $total = array_sum($outcomes);
        $latency = $this->latency(TelemetryType::AiCall, $since);

        return [
            'total' => $total,
            'by_outcome' => $outcomes,
            'fallback_rate' => $total > 0 ? round($outcomes['fallback'] / $total, 4) : 0.0,
            'failure_rate' => $total > 0 ? round($outcomes['failure'] / $total, 4) : 0.0,
            'latency_ms' => $latency,
            'by_feature' => (object) $this->nameCounts(TelemetryType::AiCall, $since),
        ];
    }

    /** @return array<string, mixed> */
    private function jobs(CarbonImmutable $since): array
    {
        $outcomes = $this->outcomeCounts(TelemetryType::IntakeJob, $since);
        $total = array_sum($outcomes);

        return [
            'total' => $total,
            'by_outcome' => $outcomes,
            'failure_rate' => $total > 0 ? round(($outcomes['failure'] + $outcomes['fallback']) / $total, 4) : 0.0,
            'by_job' => (object) $this->nameCounts(TelemetryType::IntakeJob, $since),
        ];
    }

    /** @return array<string, mixed> */
    private function errors(CarbonImmutable $since): array
    {
        $total = (int) $this->base(TelemetryType::Error, $since)->count();

        return [
            'total' => $total,
            'by_code' => (object) $this->nameCounts(TelemetryType::Error, $since),
        ];
    }

    /**
     * @return array{success: int, failure: int, fallback: int, degraded: int}
     */
    private function outcomeCounts(TelemetryType $type, CarbonImmutable $since): array
    {
        /** @var array<string, int> $rows */
        $rows = $this->base($type, $since)
            ->groupBy('outcome')
            ->selectRaw('outcome, COUNT(*) as total')
            ->pluck('total', 'outcome')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        return [
            'success' => (int) ($rows['success'] ?? 0),
            'failure' => (int) ($rows['failure'] ?? 0),
            'fallback' => (int) ($rows['fallback'] ?? 0),
            'degraded' => (int) ($rows['degraded'] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function nameCounts(TelemetryType $type, CarbonImmutable $since): array
    {
        /** @var array<string, int> $rows */
        $rows = $this->base($type, $since)
            ->groupBy('name')
            ->selectRaw('name, COUNT(*) as total')
            ->orderByDesc('total')
            ->orderBy('name')
            ->limit(10)
            ->pluck('total', 'name')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        return $rows;
    }

    /**
     * @return array{p50: int|null, p95: int|null, p99: int|null}
     */
    private function latency(TelemetryType $type, CarbonImmutable $since): array
    {
        $row = $this->base($type, $since)
            ->whereNotNull('duration_ms')
            ->selectRaw('percentile_cont(0.50) WITHIN GROUP (ORDER BY duration_ms) AS p50')
            ->selectRaw('percentile_cont(0.95) WITHIN GROUP (ORDER BY duration_ms) AS p95')
            ->selectRaw('percentile_cont(0.99) WITHIN GROUP (ORDER BY duration_ms) AS p99')
            ->first();

        if ($row === null) {
            return ['p50' => null, 'p95' => null, 'p99' => null];
        }

        return [
            'p50' => $this->asIntOrNull($row->p50 ?? null),
            'p95' => $this->asIntOrNull($row->p95 ?? null),
            'p99' => $this->asIntOrNull($row->p99 ?? null),
        ];
    }

    private function base(TelemetryType $type, CarbonImmutable $since): Builder
    {
        return DB::table('telemetry_events')
            ->where('type', $type->value)
            ->where('occurred_at', '>=', $since);
    }

    private function asIntOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) round((float) $value);
    }
}
