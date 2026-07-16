<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Dashboard\Concerns\InteractsWithDashboard;
use Tests\TestCase;

final class DashboardEmptyStateTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithDashboard;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_a_new_user_receives_a_complete_honest_empty_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson($this->url())
            ->assertOk();

        $response->assertJsonPath('data.cover.institution', null)
            ->assertJsonPath('data.cover.term', null)
            ->assertJsonPath('data.cover.active_course_count', 0)
            ->assertJsonPath('data.quick_intake.active_item', null)
            ->assertJsonPath('data.quick_intake.awaiting_review_count', 0)
            ->assertJsonPath('data.whats_next.tasks', [])
            ->assertJsonPath('data.whats_next.overdue_count', 0)
            ->assertJsonPath('data.whats_next.upcoming_count', 0)
            ->assertJsonPath('data.tools', [])
            ->assertJsonPath('data.today.due_task_count', 0)
            ->assertJsonPath('data.today.due_tasks', [])
            ->assertJsonPath('data.today.next_focus_session', null)
            ->assertJsonPath('data.second_brain.total_item_count', 0)
            ->assertJsonPath('data.second_brain.recent_items', [])
            ->assertJsonPath('data.progress.completed_task_count', 0)
            ->assertJsonPath('data.progress.due_task_count', 0)
            ->assertJsonPath('data.progress.focus_minutes', 0)
            ->assertJsonPath('data.progress.summary', 'No planner activity recorded this week yet.')
            ->assertJsonPath('data.progress.next_action', null)
            ->assertJsonPath('data.personal_rhythm.has_activity', false);

        self::assertCount(7, $response->json('data.progress.daily_completed'));
        self::assertCount(7, $response->json('data.personal_rhythm.days'));
    }
}
