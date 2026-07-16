<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Models\Course;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Onboarding\Models\UserProfile;
use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\UserToolPreference;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Dashboard\Concerns\InteractsWithDashboard;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithDashboard;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_dashboard_returns_the_approved_hierarchy_with_truthful_data(): void
    {
        $timezone = 'Asia/Dhaka';
        $now = CarbonImmutable::now('UTC');
        $localNow = $now->setTimezone($timezone);
        $user = User::factory()->create(['name' => 'Dashboard Student']);
        DB::transaction(static function () use ($user, $now): void {
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
            DB::table('onboarding_progress')->insert([
                'user_id' => $user->getKey(),
                'institution_state' => 'completed',
                'program_state' => 'completed',
                'study_stage_state' => 'completed',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            (new UserProfile)->forceFill([
                'user_id' => $user->getKey(),
                'institution_name' => 'Dhaka University',
                'institution_country_code' => 'BD',
                'degree' => 'BSc',
                'major' => 'Computer Science',
                'year_label' => '3rd year',
            ])->save();
        });

        $term = AcademicTerm::factory()->for($user, 'user')->create([
            'label' => 'Current Term',
            'starts_on' => $now->subMonth()->format('Y-m-d'),
            'ends_on' => $now->addMonths(2)->format('Y-m-d'),
        ]);
        $course = Course::factory()->for($user, 'user')->create(['title' => 'Distributed Systems']);
        Course::factory()->for($user, 'user')->archived()->create();

        $overdue = Task::factory()->for($user, 'user')->forCourse($course)->create([
            'title' => 'Overdue lab report',
            'due_at' => $now->subDay(),
        ]);
        Task::factory()->for($user, 'user')->create([
            'title' => 'Due later today',
            'due_at' => $localNow->endOfDay()->utc(),
        ]);
        Task::factory()->for($user, 'user')->create([
            'title' => 'Completed this week',
            'status' => TaskStatus::Completed->value,
            'completed_at' => $now,
            'due_at' => $now->addDay(),
        ]);
        Task::factory()->for($user, 'user')->create([
            'title' => 'Archived distraction',
            'archived_at' => $now,
            'due_at' => $now->addHour(),
        ]);

        FocusSession::factory()->for($user, 'user')->create([
            'starts_at' => $now->addHour(),
            'ends_at' => $now->addHour()->addMinutes(50),
        ]);

        $savedTool = Tool::factory()->published()->create(['name' => 'Saved Tool']);
        UserToolPreference::factory()->forUser($user)->forTool($savedTool)->saved()->create();
        $dismissedTool = Tool::factory()->published()->create(['name' => 'Dismissed Tool']);
        UserToolPreference::factory()->forUser($user)->forTool($dismissedTool)->dismissed()->create();
        Tool::factory()->published()->create(['name' => 'Fresh Tool']);
        Tool::factory()->create(['name' => 'Draft Tool']);

        KnowledgeItem::factory()->for($user, 'user')->create(['title' => 'Recent knowledge']);
        IntakeItem::factory()->queued()->create(['user_id' => $user->getKey()]);
        IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);

        $this->actingAs($user, 'web');
        $response = $this->withHeaders($this->headers())
            ->getJson($this->url($timezone))
            ->assertOk();

        $response->assertJsonPath('data.timeframe.timezone', $timezone)
            ->assertJsonPath('data.timeframe.today', $localNow->format('Y-m-d'));

        $response->assertJsonPath('data.cover.name', 'Dashboard Student')
            ->assertJsonPath('data.cover.institution', 'Dhaka University')
            ->assertJsonPath('data.cover.degree', 'BSc')
            ->assertJsonPath('data.cover.major', 'Computer Science')
            ->assertJsonPath('data.cover.study_stage', '3rd year')
            ->assertJsonPath('data.cover.term.id', (string) $term->public_id)
            ->assertJsonPath('data.cover.active_course_count', 1);

        $response->assertJsonPath('data.quick_intake.awaiting_review_count', 1);
        self::assertNotNull($response->json('data.quick_intake.active_item'));

        $response->assertJsonPath('data.whats_next.tasks.0.id', (string) $overdue->public_id)
            ->assertJsonPath('data.whats_next.tasks.0.overdue', true)
            ->assertJsonPath('data.whats_next.tasks.0.course.title', 'Distributed Systems')
            ->assertJsonPath('data.whats_next.overdue_count', 1)
            ->assertJsonPath('data.whats_next.upcoming_count', 1);
        $whatsNextTitles = array_column($response->json('data.whats_next.tasks'), 'title');
        self::assertNotContains('Archived distraction', $whatsNextTitles);
        self::assertNotContains('Completed this week', $whatsNextTitles);

        $toolNames = array_column($response->json('data.tools'), 'name');
        self::assertSame('Saved Tool', $toolNames[0]);
        self::assertContains('Fresh Tool', $toolNames);
        self::assertNotContains('Dismissed Tool', $toolNames);
        self::assertNotContains('Draft Tool', $toolNames);
        $response->assertJsonPath('data.tools.0.saved', true);

        $response->assertJsonPath('data.today.due_task_count', 1)
            ->assertJsonPath('data.today.due_tasks.0.title', 'Due later today');
        self::assertNotNull($response->json('data.today.next_focus_session'));

        $response->assertJsonPath('data.second_brain.total_item_count', 1)
            ->assertJsonPath('data.second_brain.recent_items.0.title', 'Recent knowledge');

        $response->assertJsonPath('data.progress.timeframe.timezone', $timezone)
            ->assertJsonPath('data.progress.completed_task_count', 1)
            ->assertJsonPath('data.progress.focus_minutes', 50)
            ->assertJsonPath('data.progress.next_action.kind', 'task')
            ->assertJsonPath('data.progress.next_action.id', (string) $overdue->public_id);
        $dailyCompleted = $response->json('data.progress.daily_completed');
        self::assertCount(7, $dailyCompleted);
        self::assertSame(1, array_sum(array_column($dailyCompleted, 'completed')));
        self::assertIsString($response->json('data.progress.summary'));
        self::assertStringContainsString('You completed 1', $response->json('data.progress.summary'));

        $response->assertJsonPath('data.personal_rhythm.has_activity', true);
        self::assertCount(7, $response->json('data.personal_rhythm.days'));
        self::assertSame(50, array_sum(array_column($response->json('data.personal_rhythm.days'), 'focus_minutes')));
    }

    public function test_dashboard_is_owner_scoped_and_bounded_in_queries(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        Task::factory()->for($stranger, 'user')->create(['due_at' => now()->addHour()]);
        KnowledgeItem::factory()->for($stranger, 'user')->create();
        IntakeItem::factory()->queued()->create(['user_id' => $stranger->getKey()]);
        Task::factory()->count(8)->for($user, 'user')->create(['due_at' => now()->addDay()]);
        KnowledgeItem::factory()->count(7)->for($user, 'user')->create();

        $this->actingAs($user, 'web');
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $startedAt = hrtime(true);
        $response = $this->withHeaders($this->headers())
            ->getJson($this->url())
            ->assertOk();
        $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;

        self::assertLessThanOrEqual(
            25,
            $queries,
            "The dashboard aggregate must stay bounded; observed {$queries} queries.",
        );
        self::assertLessThan(1500, $elapsedMs, 'The dashboard read exceeded the local latency guard.');

        self::assertCount(5, $response->json('data.whats_next.tasks'));
        $response->assertJsonPath('data.whats_next.upcoming_count', 8);
        self::assertCount(5, $response->json('data.second_brain.recent_items'));
        $response->assertJsonPath('data.second_brain.total_item_count', 7);
        $response->assertJsonPath('data.quick_intake.active_item', null);
        $response->assertJsonPath('data.quick_intake.awaiting_review_count', 0);
    }

    public function test_progress_never_fabricates_activity(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user, 'user')->create([
            'title' => 'Completed long ago',
            'status' => TaskStatus::Completed->value,
            'completed_at' => now()->subMonths(2),
            'due_at' => now()->subMonths(2),
        ]);

        $this->actingAs($user, 'web');
        $response = $this->withHeaders($this->headers())
            ->getJson($this->url())
            ->assertOk();

        $response->assertJsonPath('data.progress.completed_task_count', 0)
            ->assertJsonPath('data.progress.due_task_count', 0)
            ->assertJsonPath('data.progress.focus_minutes', 0)
            ->assertJsonPath('data.progress.summary', 'No planner activity recorded this week yet.')
            ->assertJsonPath('data.progress.next_action', null)
            ->assertJsonPath('data.personal_rhythm.has_activity', false);
    }

    public function test_next_action_falls_back_to_awaiting_intake_review(): void
    {
        $user = User::factory()->create();
        $reviewItem = IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);

        $this->actingAs($user, 'web');
        $response = $this->withHeaders($this->headers())
            ->getJson($this->url())
            ->assertOk();

        $response->assertJsonPath('data.progress.next_action.kind', 'intake_review')
            ->assertJsonPath('data.progress.next_action.id', (string) $reviewItem->public_id);
    }
}
