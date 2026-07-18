<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Domains\Intake\Jobs\ClassifyIntakeItem;
use App\Domains\Intake\Jobs\ProcessIntakeItem;
use App\Domains\Intake\Models\IntakeItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class IntakeJobResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_processing_job_frees_the_item_from_extracting(): void
    {
        $item = IntakeItem::factory()->extracting()->create(['attempts' => 1]);

        (new ProcessIntakeItem((int) $item->getKey()))->failed(new RuntimeException('worker killed'));

        $item->refresh();
        self::assertSame('failed_retryable', $item->state->value);
        self::assertSame('interrupted', $item->failure_code?->value);
    }

    public function test_a_failed_processing_job_at_the_ceiling_fails_final(): void
    {
        $item = IntakeItem::factory()->extracting()->create([
            'attempts' => (int) config('intake.max_attempts'),
        ]);

        (new ProcessIntakeItem((int) $item->getKey()))->failed(new RuntimeException('worker killed'));

        self::assertSame('failed_final', $item->refresh()->state->value);
    }

    public function test_a_failed_processing_job_ignores_items_that_are_not_extracting(): void
    {
        $item = IntakeItem::factory()->extracted()->create();

        (new ProcessIntakeItem((int) $item->getKey()))->failed(new RuntimeException('worker killed'));

        self::assertSame('extracted', $item->refresh()->state->value);
    }

    public function test_a_failed_classification_job_frees_the_item_from_organizing(): void
    {
        $item = IntakeItem::factory()->organizing()->create(['attempts' => 1]);

        (new ClassifyIntakeItem((int) $item->getKey()))->failed(new RuntimeException('worker killed'));

        $item->refresh();
        self::assertSame('failed_retryable', $item->state->value);
        self::assertSame('classification_failed', $item->failure_code?->value);
    }
}
