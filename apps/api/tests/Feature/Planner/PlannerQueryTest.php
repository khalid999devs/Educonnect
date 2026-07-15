<?php

declare(strict_types=1);

namespace Tests\Feature\Planner;

use App\Domains\Courses\Models\Course;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PlannerQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }

    public function test_agenda_uses_a_dst_safe_half_open_day_and_bounded_overlap_results(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $owner->getKey()]);
        Task::factory()->forCourse($course)->dueAt(CarbonImmutable::parse('2026-03-08T05:00:00Z'))->create([
            'title' => 'Start boundary task',
        ]);
        Task::factory()->forCourse($course)->dueAt(CarbonImmutable::parse('2026-03-09T03:59:59Z'))->create([
            'title' => 'End boundary task',
        ]);
        Task::factory()->forCourse($course)->dueAt(CarbonImmutable::parse('2026-03-09T04:00:00Z'))->create([
            'title' => 'Excluded next-day task',
        ]);
        Task::factory()->forCourse($course)->dueAt(CarbonImmutable::parse('2026-03-08T12:00:00Z'))->archived()->create([
            'title' => 'Archived private task',
        ]);
        Task::factory()->dueAt(CarbonImmutable::parse('2026-03-08T12:00:00Z'))->create([
            'user_id' => $other->getKey(),
            'title' => 'Foreign private task',
        ]);
        FocusSession::factory()->forCourse($course)->between(
            CarbonImmutable::parse('2026-03-08T04:30:00Z'),
            CarbonImmutable::parse('2026-03-08T05:30:00Z'),
        )->create(['note' => 'Overlaps the start']);
        FocusSession::factory()->forCourse($course)->between(
            CarbonImmutable::parse('2026-03-09T03:30:00Z'),
            CarbonImmutable::parse('2026-03-09T04:30:00Z'),
        )->create(['note' => 'Overlaps the end']);
        FocusSession::factory()->forCourse($course)->between(
            CarbonImmutable::parse('2026-03-08T04:00:00Z'),
            CarbonImmutable::parse('2026-03-08T05:00:00Z'),
        )->create(['note' => 'Ends exactly at the start']);
        $focusQueries = [];
        DB::listen(static function (QueryExecuted $query) use (&$focusQueries): void {
            if (str_contains($query->sql, 'from "focus_sessions"')) {
                $focusQueries[] = $query->sql;
            }
        });

        $this->actingAs($owner, 'web');
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/planner/agenda?timezone=America%2FNew_York&date=2026-03-08&limit=1')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'America/New_York')
            ->assertJsonPath('data.date', '2026-03-08')
            ->assertJsonPath('data.window.starts_at', '2026-03-08T05:00:00.000000Z')
            ->assertJsonPath('data.window.ends_at', '2026-03-09T04:00:00.000000Z')
            ->assertJsonPath('meta.summary.tasks', 2)
            ->assertJsonPath('meta.summary.focus_sessions', 2)
            ->assertJsonPath('meta.has_more.tasks', true)
            ->assertJsonPath('meta.has_more.focus_sessions', true)
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonCount(1, 'data.focus_sessions');

        $payload = (string) $response->getContent();
        self::assertStringNotContainsString('Archived private task', $payload);
        self::assertStringNotContainsString('Foreign private task', $payload);
        self::assertStringNotContainsString('Ends exactly at the start', $payload);
        self::assertNotEmpty($focusQueries);
        self::assertTrue(collect($focusQueries)->contains(
            static fn (string $query): bool => str_contains($query, '"starts_at" > ?')
                && str_contains($query, '"starts_at" < ?')
                && str_contains($query, '"ends_at" > ?'),
        ));
    }

    public function test_weekly_window_spans_seven_local_days_across_a_twenty_five_hour_day(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $owner->getKey()]);
        Task::factory()->forCourse($course)->dueAt(CarbonImmutable::parse('2026-10-31T04:00:00Z'))->create([
            'title' => 'Weekly start boundary',
        ]);
        Task::factory()->forCourse($course)->dueAt(CarbonImmutable::parse('2026-11-07T04:59:59Z'))->create([
            'title' => 'Weekly end boundary',
        ]);
        Task::factory()->forCourse($course)->dueAt(CarbonImmutable::parse('2026-11-07T05:00:00Z'))->create([
            'title' => 'Excluded next week',
        ]);
        Task::factory()->forCourse($course)->dueAt(CarbonImmutable::parse('2026-11-02T12:00:00Z'))->archived()->create([
            'title' => 'Archived weekly task',
        ]);
        Task::factory()->dueAt(CarbonImmutable::parse('2026-11-02T12:00:00Z'))->create([
            'user_id' => $other->getKey(),
            'title' => 'Foreign weekly task',
        ]);
        FocusSession::factory()->forCourse($course)->between(
            CarbonImmutable::parse('2026-10-31T03:30:00Z'),
            CarbonImmutable::parse('2026-10-31T04:30:00Z'),
        )->create(['note' => 'Weekly start overlap']);
        FocusSession::factory()->forCourse($course)->between(
            CarbonImmutable::parse('2026-11-07T04:30:00Z'),
            CarbonImmutable::parse('2026-11-07T05:30:00Z'),
        )->create(['note' => 'Weekly end overlap']);
        FocusSession::factory()->forCourse($course)->between(
            CarbonImmutable::parse('2026-10-31T03:00:00Z'),
            CarbonImmutable::parse('2026-10-31T04:00:00Z'),
        )->create(['note' => 'Weekly exact-start exclusion']);
        $this->actingAs($owner, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/planner/weekly?timezone=America%2FNew_York&week_start=2026-10-31&limit=1')
            ->assertOk()
            ->assertJsonPath('data.week_start', '2026-10-31')
            ->assertJsonPath('data.window.starts_at', '2026-10-31T04:00:00.000000Z')
            ->assertJsonPath('data.window.ends_at', '2026-11-07T05:00:00.000000Z')
            ->assertJsonPath('meta.summary.tasks', 2)
            ->assertJsonPath('meta.summary.focus_sessions', 2)
            ->assertJsonPath('meta.has_more.tasks', true)
            ->assertJsonPath('meta.has_more.focus_sessions', true)
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonCount(1, 'data.focus_sessions');

        $payload = (string) $response->getContent();
        self::assertStringNotContainsString('Excluded next week', $payload);
        self::assertStringNotContainsString('Archived weekly task', $payload);
        self::assertStringNotContainsString('Foreign weekly task', $payload);
        self::assertStringNotContainsString('Weekly exact-start exclusion', $payload);
    }

    public function test_task_and_focus_lists_are_owner_scoped_filterable_and_use_private_stable_cursors(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $owner->getKey()]);
        $timestamp = CarbonImmutable::parse('2026-07-14T08:00:00Z');
        $dueAt = CarbonImmutable::parse('2026-07-15T08:00:00Z');
        $first = Task::factory()->forCourse($course)->dueAt($dueAt)->create([
            'title' => 'Private Alpha Task',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $second = Task::factory()->forCourse($course)->dueAt($dueAt->addHour())->create([
            'title' => 'Private Beta Task',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        Task::factory()->forCourse($course)->dueAt($dueAt)->archived()->create([
            'title' => 'Private archived task',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        Task::factory()->create([
            'user_id' => $other->getKey(),
            'title' => 'Foreign private task',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $focusA = FocusSession::factory()->forTask($first)->between(
            CarbonImmutable::parse('2026-07-15T07:30:00Z'),
            CarbonImmutable::parse('2026-07-15T08:30:00Z'),
        )->create(['note' => 'Private focus A']);
        FocusSession::factory()->forTask($first)->between(
            CarbonImmutable::parse('2026-07-15T08:30:00Z'),
            CarbonImmutable::parse('2026-07-15T09:30:00Z'),
        )->create(['note' => 'Private focus B']);

        $this->actingAs($owner, 'web');
        $taskPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tasks?status=pending&archive_status=active&has_due=true&sort=updated_at&per_page=1&course_id='.$course->public_id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $taskCursor = $taskPage->json('meta.pagination.next_cursor');
        self::assertIsString($taskCursor);
        $decodedTask = Cursor::fromEncoded($taskCursor);
        self::assertInstanceOf(Cursor::class, $decodedTask);
        self::assertSame(
            ['cursor_updated_at_asc', 'public_id', '_pointsToNextItems'],
            array_keys($decodedTask->toArray()),
        );
        $taskCursorJson = json_encode($decodedTask->toArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Private Alpha Task', $taskCursorJson);
        self::assertStringNotContainsString('Private Beta Task', $taskCursorJson);

        $taskSecondPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tasks?status=pending&archive_status=active&has_due=true&sort=updated_at&per_page=1&course_id='.$course->public_id.'&cursor='.rawurlencode($taskCursor))
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $listedTaskIds = [$taskPage->json('data.0.id'), $taskSecondPage->json('data.0.id')];
        sort($listedTaskIds);
        $expectedTaskIds = [$first->public_id, $second->public_id];
        sort($expectedTaskIds);
        self::assertSame($expectedTaskIds, $listedTaskIds);

        $focusPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/focus-sessions?task_id='.$first->public_id.'&overlap_from=2026-07-15T08%3A00%3A00Z&overlap_before=2026-07-15T09%3A00%3A00Z&sort=starts_at&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $focusA->public_id);
        $focusCursor = $focusPage->json('meta.pagination.next_cursor');
        self::assertIsString($focusCursor);
        $decodedFocus = Cursor::fromEncoded($focusCursor);
        self::assertInstanceOf(Cursor::class, $decodedFocus);
        self::assertSame(
            ['cursor_starts_at_asc', 'public_id', '_pointsToNextItems'],
            array_keys($decodedFocus->toArray()),
        );
        self::assertStringNotContainsString(
            'Private focus A',
            json_encode($decodedFocus->toArray(), JSON_THROW_ON_ERROR),
        );

        $focusSecondPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/focus-sessions?task_id='.$first->public_id
                .'&overlap_from=2026-07-15T08%3A00%3A00Z'
                .'&overlap_before=2026-07-15T09%3A00%3A00Z&sort=starts_at&per_page=1'
                .'&cursor='.rawurlencode($focusCursor))
            ->assertOk()
            ->assertJsonCount(1, 'data');
        self::assertNotSame($focusPage->json('data.0.id'), $focusSecondPage->json('data.0.id'));

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tasks?sort=-updated_at&cursor='.rawurlencode($taskCursor))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tasks?sort=created_at')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $forgedTaskCursor = (new Cursor([
            'cursor_updated_at_asc' => 'next Thursday',
            'public_id' => $first->public_id,
        ]))->encode();
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/tasks?sort=updated_at&cursor='.rawurlencode($forgedTaskCursor))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/focus-sessions?sort=-starts_at&cursor='.rawurlencode($focusCursor))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/focus-sessions?task_id='.$first->public_id.'&course_id='.$course->public_id)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'planner-query-test-token',
        ];
    }
}
