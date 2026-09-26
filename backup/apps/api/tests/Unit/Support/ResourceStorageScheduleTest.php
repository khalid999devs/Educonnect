<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ResourceStorageScheduleTest extends TestCase
{
    public function test_reconciler_batch_is_bounded_inside_a_longer_overlap_lock(): void
    {
        Artisan::call('list');

        $events = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            static fn (Event $event): bool => is_string($event->command)
                && str_contains($event->command, 'resources:reconcile-storage'),
        ));

        $this->assertCount(1, $events);
        $event = $events[0];

        $this->assertStringContainsString('resources:reconcile-storage --limit=10', (string) $event->command);
        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(120, $event->expiresAt);
    }
}
