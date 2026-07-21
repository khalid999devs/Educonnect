<?php

declare(strict_types=1);

namespace Tests\Feature\Progress;

use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\Resources\Models\Resource;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\KnowledgeNote;
use App\Domains\Templates\Models\UserTemplateCopy;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsApiResponses;
use Tests\Concerns\CreatesRetainedResearchRows;
use Tests\Feature\Progress\Concerns\InteractsWithProgress;
use Tests\TestCase;

final class ProgressTest extends TestCase
{
    use AssertsApiResponses;
    use CreatesRetainedResearchRows;
    use InteractsWithProgress;
    use RefreshDatabase;

    private const TIMEZONE = 'Asia/Dhaka';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
        /* Frozen at 14:00 Asia/Dhaka on Wednesday 2026-07-15, so every fixture
           built relative to now stays inside the local day and the local
           Monday-anchored week, whatever hour the suite runs at. */
        $this->travelTo(CarbonImmutable::parse('2026-07-15T08:00:00Z'));
    }

    public function test_progress_reports_real_counts_from_every_tracked_signal(): void
    {
        $user = User::factory()->create();
        $this->seedOneOfEverySignal($user);
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson($this->url(self::TIMEZONE, 'week'))
            ->assertOk();

        $response
            ->assertJsonPath('data.timeframe.timezone', self::TIMEZONE)
            ->assertJsonPath('data.timeframe.window', 'week')
            ->assertJsonPath('data.timeframe.starts_on', '2026-07-13')
            ->assertJsonPath('data.timeframe.ends_on', '2026-07-19')
            ->assertJsonPath('data.has_activity', true)
            ->assertJsonPath('data.totals.tasks_completed', 1)
            ->assertJsonPath('data.totals.focus_minutes', 45)
            ->assertJsonPath('data.totals.resources_added', 1)
            ->assertJsonPath('data.totals.intake_items_processed', 1)
            ->assertJsonPath('data.totals.knowledge_items_added', 1)
            ->assertJsonPath('data.totals.notes_written', 1)
            ->assertJsonPath('data.totals.template_copies_created', 1)
            ->assertJsonPath('data.totals.research_sources_reviewed', 1);

        $daily = $response->json('data.daily');
        self::assertIsArray($daily);
        self::assertCount(7, $daily);
        self::assertSame(1, array_sum(array_column($daily, 'tasks_completed')));
        self::assertSame(45, array_sum(array_column($daily, 'focus_minutes')));

        $today = array_values(array_filter(
            $daily,
            static fn (array $day): bool => $day['date'] === '2026-07-15',
        ));
        self::assertCount(1, $today);
        self::assertSame(1, $today[0]['tasks_completed']);
        self::assertSame(45, $today[0]['focus_minutes']);
    }

    public function test_an_empty_window_says_so_plainly_instead_of_inventing_encouragement(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson($this->url(self::TIMEZONE, 'week'))
            ->assertOk();

        $response
            ->assertJsonPath('data.has_activity', false)
            ->assertJsonPath('data.summary', 'No activity recorded this week yet.')
            ->assertJsonPath('data.next_action', null)
            ->assertJsonPath('data.activity_rhythm.has_activity', false);

        foreach ((array) $response->json('data.totals') as $total) {
            self::assertSame(0, $total);
        }

        foreach ((array) $response->json('data.activity_rhythm.days') as $day) {
            self::assertIsArray($day);
            self::assertFalse($day['was_active']);
        }
    }

    /**
     * PRODUCT INVARIANT (README, docs/educonnect/04, ADR-0018): progress is
     * real records only. No consecutive-day counter, no best streak, no flame
     * or badge, no percentile, no "vs last week" delta. This test guards both
     * the wire format and the source, because the failure mode is somebody
     * helpfully adding a "streak" field later.
     */
    public function test_progress_never_ships_a_streak_or_any_other_vanity_metric(): void
    {
        $user = User::factory()->create();
        $this->seedOneOfEverySignal($user);
        $this->actingAs($user, 'web');

        $payload = $this->withHeaders($this->headers())
            ->getJson($this->url(self::TIMEZONE, 'week'))
            ->assertOk()
            ->json('data');

        $encoded = json_encode($payload);
        self::assertIsString($encoded);

        foreach ($this->forbiddenVocabulary() as $forbidden) {
            self::assertStringNotContainsStringIgnoringCase(
                $forbidden,
                $encoded,
                sprintf('The progress payload must never express "%s".', $forbidden),
            );
        }

        $sourceRoot = app_path('Domains/Progress');
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        $files[] = app_path('Http/Resources/ProgressResource.php');
        self::assertNotEmpty($files);

        /* The doc blocks name these concepts to forbid them, so the source
           check looks for them as identifiers rather than as prose. */
        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $code = (string) preg_replace('#/\*\*.*?\*/#s', '', $source);

            foreach (['streak', 'flame', 'badge', 'percentile', 'longest_run', 'best_run'] as $forbidden) {
                self::assertStringNotContainsStringIgnoringCase(
                    $forbidden,
                    $code,
                    sprintf('%s must not reference "%s".', basename($file), $forbidden),
                );
            }
        }
    }

    public function test_the_activity_rhythm_returns_seven_days_of_real_signals(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user, 'user')->completed(CarbonImmutable::now())->create();
        Resource::factory()->create([
            'user_id' => $user->getKey(),
            'created_at' => CarbonImmutable::now()->subDays(2),
        ]);
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson($this->url(self::TIMEZONE))
            ->assertOk();

        $days = $response->json('data.activity_rhythm.days');
        self::assertIsArray($days);
        self::assertCount(7, $days);
        self::assertSame('2026-07-09', $days[0]['date']);
        self::assertSame('2026-07-15', $days[6]['date']);

        $active = array_values(array_filter($days, static fn (array $day): bool => $day['was_active'] === true));
        self::assertCount(2, $active);
        self::assertSame(['tasks', 'focus_minutes', 'resources', 'notes'], array_keys($days[0]['signals']));
        $response->assertJsonPath('data.activity_rhythm.has_activity', true);
    }

    public function test_the_month_window_starts_at_the_local_first_of_month(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user, 'user')->completed(CarbonImmutable::now()->subDays(10))->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson($this->url(self::TIMEZONE, 'month'))
            ->assertOk()
            ->assertJsonPath('data.timeframe.window', 'month')
            ->assertJsonPath('data.timeframe.starts_on', '2026-07-01')
            ->assertJsonPath('data.timeframe.ends_on', '2026-07-15')
            ->assertJsonPath('data.totals.tasks_completed', 1);
    }

    public function test_the_term_window_uses_the_active_academic_term_and_names_it(): void
    {
        $user = User::factory()->create();
        AcademicTerm::factory()->create([
            'user_id' => $user->getKey(),
            'label' => 'Summer 2026',
            'starts_on' => '2026-07-06',
            'ends_on' => '2026-11-30',
        ]);
        Task::factory()->for($user, 'user')->completed(CarbonImmutable::now()->subDays(5))->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson($this->url(self::TIMEZONE, 'term'))
            ->assertOk()
            ->assertJsonPath('data.timeframe.window', 'term')
            ->assertJsonPath('data.timeframe.starts_on', '2026-07-06')
            ->assertJsonPath('data.timeframe.ends_on', '2026-07-15')
            ->assertJsonPath('data.timeframe.term_label', 'Summer 2026')
            ->assertJsonPath('data.totals.tasks_completed', 1);
    }

    public function test_the_term_window_states_its_real_dates_when_no_term_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson($this->url(self::TIMEZONE, 'term'))
            ->assertOk()
            ->assertJsonPath('data.timeframe.term_label', null)
            ->assertJsonPath('data.timeframe.ends_on', '2026-07-15')
            ->assertJsonPath('data.has_activity', false);
    }

    public function test_a_daily_series_is_never_unbounded(): void
    {
        $user = User::factory()->create();
        AcademicTerm::factory()->create([
            'user_id' => $user->getKey(),
            'label' => 'Long Term',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-12-31',
        ]);
        $this->actingAs($user, 'web');

        $daily = $this->withHeaders($this->headers())
            ->getJson($this->url(self::TIMEZONE, 'term'))
            ->assertOk()
            ->json('data.daily');

        self::assertIsArray($daily);
        self::assertLessThanOrEqual(92, count($daily));
    }

    public function test_progress_counts_only_the_requesting_students_own_records(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $this->seedOneOfEverySignal($stranger);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson($this->url(self::TIMEZONE, 'week'))
            ->assertOk()
            ->assertJsonPath('data.has_activity', false)
            ->assertJsonPath('data.totals.tasks_completed', 0)
            ->assertJsonPath('data.totals.knowledge_items_added', 0);
    }

    public function test_the_next_action_points_at_a_real_record(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user, 'user')->create(['due_at' => CarbonImmutable::now()->addHours(3)]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson($this->url(self::TIMEZONE, 'week'))
            ->assertOk()
            ->assertJsonPath('data.next_action.kind', 'task')
            ->assertJsonPath('data.next_action.id', (string) $task->public_id);
    }

    public function test_the_dashboard_block_and_the_progress_endpoint_agree(): void
    {
        $user = User::factory()->create();
        $this->seedOneOfEverySignal($user);
        $this->actingAs($user, 'web');

        $progress = $this->withHeaders($this->headers())
            ->getJson($this->url(self::TIMEZONE, 'week'))
            ->assertOk();
        $dashboard = $this->withHeaders($this->headers())
            ->getJson('/api/v1/dashboard?timezone='.urlencode(self::TIMEZONE))
            ->assertOk();

        self::assertSame(
            $progress->json('data.totals.tasks_completed'),
            $dashboard->json('data.progress.completed_task_count'),
        );
        self::assertSame(
            $progress->json('data.totals.focus_minutes'),
            $dashboard->json('data.progress.focus_minutes'),
        );
        self::assertSame(
            $progress->json('data.timeframe.starts_on'),
            $dashboard->json('data.progress.timeframe.starts_on'),
        );
        self::assertSame(
            array_column((array) $progress->json('data.activity_rhythm.days'), 'date'),
            array_column((array) $dashboard->json('data.personal_rhythm.days'), 'date'),
        );
        $rhythmFocus = array_sum(array_column(
            array_column((array) $progress->json('data.activity_rhythm.days'), 'signals'),
            'focus_minutes',
        ));
        self::assertSame(
            $rhythmFocus,
            array_sum(array_column((array) $dashboard->json('data.personal_rhythm.days'), 'focus_minutes')),
        );
    }

    public function test_a_guest_cannot_read_progress(): void
    {
        $this->assertApiError(
            $this->withHeaders($this->headers())->getJson($this->url(self::TIMEZONE)),
            401,
            ApiErrorCode::AuthenticationRequired,
        );
    }

    public function test_an_unknown_query_parameter_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->assertApiError(
            $this->withHeaders($this->headers())
                ->getJson($this->url(self::TIMEZONE).'&streak=true'),
            422,
            ApiErrorCode::ValidationFailed,
        );
    }

    public function test_an_unsupported_window_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->assertApiError(
            $this->withHeaders($this->headers())->getJson($this->url(self::TIMEZONE, 'decade')),
            422,
            ApiErrorCode::ValidationFailed,
        );
    }

    public function test_an_unsupported_timezone_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->assertApiError(
            $this->withHeaders($this->headers())->getJson($this->url('Not/AZone')),
            422,
            ApiErrorCode::ValidationFailed,
        );
    }

    private function seedOneOfEverySignal(User $user): void
    {
        $now = CarbonImmutable::now();

        Task::factory()->for($user, 'user')->completed($now)->create();
        FocusSession::factory()->for($user, 'user')->create([
            'starts_at' => $now->subHour(),
            'ends_at' => $now->subHour()->addMinutes(45),
        ]);
        Resource::factory()->create([
            'user_id' => $user->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        IntakeItem::factory()->saved($now)->create(['user_id' => $user->getKey()]);

        $item = KnowledgeItem::factory()->for($user, 'user')->linkSource()->create([
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        KnowledgeNote::factory()->forItem($item)->create(['created_at' => $now, 'updated_at' => $now]);
        UserTemplateCopy::factory()->create([
            'user_id' => $user->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $topicId = $this->insertResearchTopic((int) $user->getKey());
        DB::table('research_topic_sources')->insert([
            'user_id' => $user->getKey(),
            'research_topic_id' => $topicId,
            'knowledge_item_id' => $item->getKey(),
            'reading_status' => 'read',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return list<string> */
    private function forbiddenVocabulary(): array
    {
        return ['streak', 'flame', 'badge', 'percentile', 'vs last week', 'best run', 'longest', 'consecutive'];
    }
}
