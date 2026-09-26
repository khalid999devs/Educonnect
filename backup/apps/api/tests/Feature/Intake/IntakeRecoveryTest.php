<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Domains\Intake\Actions\RecoverStrandedIntakeAction;
use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Jobs\ProcessIntakeItem;
use App\Domains\Intake\Models\IntakeItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class IntakeRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Dispatched recovery jobs must not run and further mutate state here.
        Queue::fake();
    }

    public function test_stranded_processing_items_are_reaped_as_retryable(): void
    {
        $extracting = IntakeItem::factory()->extracting()->create([
            'started_at' => now()->subMinutes(10),
            'attempts' => 1,
        ]);
        $organizing = IntakeItem::factory()->organizing()->create([
            'started_at' => now()->subMinutes(10),
            'attempts' => 1,
        ]);

        $result = app(RecoverStrandedIntakeAction::class)->execute(50);

        self::assertSame(2, $result->reaped);
        self::assertSame('failed_retryable', $extracting->refresh()->state->value);
        self::assertSame(IntakeFailureCode::StrandedTimeout, $extracting->failure_code);
        self::assertSame('failed_retryable', $organizing->refresh()->state->value);
        $this->assertDatabaseHas('intake_events', [
            'intake_item_id' => $extracting->getKey(),
            'event' => 'reaped',
        ]);
    }

    public function test_a_stranded_item_at_the_attempt_ceiling_is_reaped_as_final(): void
    {
        $item = IntakeItem::factory()->extracting()->create([
            'started_at' => now()->subMinutes(10),
            'attempts' => (int) config('intake.max_attempts'),
        ]);

        app(RecoverStrandedIntakeAction::class)->execute(50);

        self::assertSame('failed_final', $item->refresh()->state->value);
        self::assertSame(IntakeFailureCode::StrandedTimeout, $item->failure_code);
    }

    public function test_recent_processing_items_are_left_alone(): void
    {
        $fresh = IntakeItem::factory()->extracting()->create([
            'started_at' => now()->subSeconds(30),
        ]);

        $result = app(RecoverStrandedIntakeAction::class)->execute(50);

        self::assertSame(0, $result->reaped);
        self::assertSame('extracting', $fresh->refresh()->state->value);
    }

    public function test_a_stranded_queue_entry_is_redispatched_without_failing(): void
    {
        $queued = IntakeItem::factory()->queued()->create([
            'queued_at' => now()->subMinutes(10),
        ]);

        $result = app(RecoverStrandedIntakeAction::class)->execute(50);

        self::assertSame(1, $result->redispatched);
        $queued->refresh();
        self::assertSame('queued', $queued->state->value);
        self::assertTrue($queued->queued_at->greaterThan(now()->subMinute()));
        Queue::assertPushed(ProcessIntakeItem::class);
    }

    public function test_backed_off_retryable_items_are_auto_requeued_within_budget(): void
    {
        $ready = IntakeItem::factory()->failedRetryable()->create([
            'attempts' => 1,
            'finished_at' => now()->subMinutes(5),
        ]);
        $backingOff = IntakeItem::factory()->failedRetryable()->create([
            'attempts' => 1,
            'finished_at' => now()->subSeconds(10),
        ]);
        $exhausted = IntakeItem::factory()->failedRetryable()->create([
            'attempts' => (int) config('intake.max_attempts'),
            'finished_at' => now()->subDay(),
        ]);

        $result = app(RecoverStrandedIntakeAction::class)->execute(50);

        self::assertSame(1, $result->requeued);
        self::assertSame('queued', $ready->refresh()->state->value);
        self::assertNull($ready->failure_code);
        self::assertSame('failed_retryable', $backingOff->refresh()->state->value);
        self::assertSame('failed_retryable', $exhausted->refresh()->state->value);
        Queue::assertPushed(ProcessIntakeItem::class);
    }

    public function test_auto_retry_can_be_disabled(): void
    {
        config()->set('intake.auto_retry.enabled', false);
        $ready = IntakeItem::factory()->failedRetryable()->create([
            'attempts' => 1,
            'finished_at' => now()->subMinutes(5),
        ]);

        $result = app(RecoverStrandedIntakeAction::class)->execute(50);

        self::assertSame(0, $result->requeued);
        self::assertSame('failed_retryable', $ready->refresh()->state->value);
    }
}
