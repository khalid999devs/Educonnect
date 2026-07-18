<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Domains\Telemetry\Enums\TelemetryOutcome;
use App\Domains\Telemetry\Models\TelemetryEvent;
use App\Domains\Telemetry\Support\TelemetryRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TelemetryRecorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('telemetry.enabled', true);
    }

    public function test_it_records_a_bounded_ai_call_event(): void
    {
        $recorder = new TelemetryRecorder;

        $recorder->recordAiCall('intake.classification', TelemetryOutcome::Success, 812, [
            'provider' => 'openai',
            'model' => 'gpt-5-mini',
        ]);

        $event = TelemetryEvent::query()->firstOrFail();
        self::assertSame('ai_call', $event->getAttribute('type'));
        self::assertSame('intake.classification', $event->getAttribute('name'));
        self::assertSame('success', $event->getAttribute('outcome'));
        self::assertSame(812, $event->getAttribute('duration_ms'));
        self::assertSame('openai', $event->getAttribute('metadata')['provider']);
    }

    public function test_it_normalizes_an_unsafe_name_to_satisfy_the_constraint(): void
    {
        $recorder = new TelemetryRecorder;

        $recorder->recordServerError(500, 'Internal Error!', ['exception' => 'RuntimeException']);

        $event = TelemetryEvent::query()->firstOrFail();
        self::assertSame('error', $event->getAttribute('type'));
        self::assertSame('internal.error', $event->getAttribute('name'));
        self::assertSame(500, $event->getAttribute('status_code'));
    }

    public function test_it_drops_non_scalar_values_and_truncates_long_strings(): void
    {
        $recorder = new TelemetryRecorder;

        $recorder->recordAiCall('copilot', TelemetryOutcome::Failure, 10, [
            'provider' => 'openai',
            'nested' => ['not', 'allowed'],
            'long' => str_repeat('x', 20000),
        ]);

        $metadata = TelemetryEvent::query()->firstOrFail()->getAttribute('metadata');
        self::assertSame('openai', $metadata['provider']);
        self::assertArrayNotHasKey('nested', $metadata);
        self::assertSame(500, mb_strlen($metadata['long']));
    }

    public function test_it_drops_metadata_that_exceeds_the_byte_cap(): void
    {
        config()->set('telemetry.max_metadata_bytes', 64);
        $recorder = new TelemetryRecorder;

        $recorder->recordAiCall('copilot', TelemetryOutcome::Failure, 10, [
            'provider' => 'openai',
            'model' => 'gpt-5-mini',
            'note' => str_repeat('detail ', 40),
        ]);

        // The whole payload trips the byte cap and is dropped to an empty object.
        self::assertSame([], TelemetryEvent::query()->firstOrFail()->getAttribute('metadata'));
    }

    public function test_recording_is_a_no_op_when_disabled(): void
    {
        config()->set('telemetry.enabled', false);
        $recorder = new TelemetryRecorder;

        $recorder->recordAiCall('copilot', TelemetryOutcome::Success, 5);

        self::assertSame(0, TelemetryEvent::query()->count());
    }
}
