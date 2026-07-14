<?php

declare(strict_types=1);

namespace Tests\Feature\Planner;

use App\Domains\Courses\Models\Course;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class PlannerLifecycleTest extends TestCase
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

    public function test_task_and_focus_lifecycles_are_versioned_retry_safe_and_explicitly_destructive(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->getKey()]);
        $this->actingAs($user, 'web');

        $createdTask = $this->withHeaders($this->headers())
            ->postJson('/api/v1/tasks', [
                'title' => '  Submit algorithms assignment  ',
                'description' => '  Complete the final proof.  ',
                'course_id' => $course->public_id,
                'due_at' => '2026-07-15T18:30:00+06:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.title', 'Submit algorithms assignment')
            ->assertJsonPath('data.description', 'Complete the final proof.')
            ->assertJsonPath('data.course.id', $course->public_id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.archive_status', 'active')
            ->assertJsonPath('data.completed_at', null)
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.course_id');
        $taskId = $createdTask->json('data.id');
        self::assertIsString($taskId);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/courses/{$course->public_id}", ['expected_version' => 1])
            ->assertConflict()
            ->assertJsonPath('error.code', 'CONFLICT');

        $updated = $this->withHeaders($this->headers())
            ->putJson("/api/v1/tasks/{$taskId}", [
                'expected_version' => 1,
                'title' => 'Submit algorithms assignment',
                'description' => null,
                'course_id' => $course->public_id,
                'due_at' => '2026-07-15T12:30:00Z',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.description', null);
        $updatedAt = $updated->json('data.updated_at');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tasks/{$taskId}", [
                'expected_version' => 1,
                'title' => 'Submit algorithms assignment',
                'description' => null,
                'course_id' => $course->public_id,
                'due_at' => '2026-07-15T12:30:00Z',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.updated_at', $updatedAt);

        $completed = $this->withHeaders($this->headers())
            ->putJson("/api/v1/tasks/{$taskId}/status", [
                'expected_version' => 2,
                'status' => 'completed',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.status', 'completed');
        $completedAt = $completed->json('data.completed_at');
        self::assertIsString($completedAt);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tasks/{$taskId}/status", [
                'expected_version' => 1,
                'status' => 'completed',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.completed_at', $completedAt);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tasks/{$taskId}/status", [
                'expected_version' => 2,
                'status' => 'in_progress',
            ])
            ->assertConflict();

        $archived = $this->withHeaders($this->headers())
            ->putJson("/api/v1/tasks/{$taskId}/archive", ['expected_version' => 3])
            ->assertOk()
            ->assertJsonPath('data.version', 4)
            ->assertJsonPath('data.archive_status', 'archived');
        $archivedAt = $archived->json('data.archived_at');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tasks/{$taskId}/archive", ['expected_version' => 1])
            ->assertOk()
            ->assertJsonPath('data.version', 4)
            ->assertJsonPath('data.archived_at', $archivedAt);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/tasks/{$taskId}/archive", ['expected_version' => 4])
            ->assertOk()
            ->assertJsonPath('data.version', 5)
            ->assertJsonPath('data.archive_status', 'active');

        $focus = $this->withHeaders($this->headers())
            ->postJson('/api/v1/focus-sessions', [
                'task_id' => $taskId,
                'course_id' => null,
                'starts_at' => '2026-07-15T10:00:00Z',
                'ends_at' => '2026-07-15T10:50:00Z',
                'note' => '  Work without notifications.  ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.task.id', $taskId)
            ->assertJsonPath('data.course', null)
            ->assertJsonPath('data.note', 'Work without notifications.')
            ->assertJsonMissingPath('data.task_id')
            ->assertJsonMissingPath('data.user_id');
        $focusId = $focus->json('data.id');
        self::assertIsString($focusId);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/tasks/{$taskId}", ['expected_version' => 5])
            ->assertConflict()
            ->assertJsonPath('error.code', 'CONFLICT');

        $updatedFocus = $this->withHeaders($this->headers())
            ->putJson("/api/v1/focus-sessions/{$focusId}", [
                'expected_version' => 1,
                'task_id' => null,
                'course_id' => null,
                'starts_at' => '2026-07-15T11:00:00Z',
                'ends_at' => '2026-07-15T12:00:00Z',
                'note' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.task', null)
            ->assertJsonPath('data.course', null);
        $focusUpdatedAt = $updatedFocus->json('data.updated_at');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/focus-sessions/{$focusId}", [
                'expected_version' => 1,
                'task_id' => null,
                'course_id' => null,
                'starts_at' => '2026-07-15T11:00:00Z',
                'ends_at' => '2026-07-15T12:00:00Z',
                'note' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.updated_at', $focusUpdatedAt);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/focus-sessions/{$focusId}", [
                'expected_version' => 1,
                'task_id' => null,
                'course_id' => null,
                'starts_at' => '2026-07-15T11:00:00Z',
                'ends_at' => '2026-07-15T12:00:00Z',
                'note' => 'Stale overwrite',
            ])
            ->assertConflict();

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/focus-sessions/{$focusId}", ['expected_version' => 1])
            ->assertConflict();

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/tasks/{$taskId}", ['expected_version' => 5])
            ->assertNoContent();

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/focus-sessions/{$focusId}", ['expected_version' => 2])
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['public_id' => $taskId]);
        $this->assertDatabaseMissing('focus_sessions', ['public_id' => $focusId]);
    }

    public function test_completed_tasks_can_reopen_without_retaining_completion_state(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->completed()->create(['user_id' => $user->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tasks/{$task->public_id}/status", [
                'expected_version' => 1,
                'status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.completed_at', null);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tasks/{$task->public_id}/status", [
                'expected_version' => 1,
                'status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.completed_at', null);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tasks/{$task->public_id}/status", [
                'expected_version' => 1,
                'status' => 'pending',
            ])
            ->assertConflict();
    }

    public function test_archived_relationship_targets_reject_new_assignments_but_allow_exact_retries(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->getKey()]);
        $task = Task::factory()->create(['user_id' => $user->getKey()]);
        $linkedFocus = FocusSession::factory()->forTask($task)->create();
        $unlinkedFocus = FocusSession::factory()->create(['user_id' => $user->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/courses/{$course->public_id}/archive", ['expected_version' => 1])
            ->assertOk();

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/tasks', [
                'title' => 'Cannot assign an archived course',
                'description' => null,
                'course_id' => $course->public_id,
                'due_at' => null,
            ])
            ->assertConflict();

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tasks/{$task->public_id}/archive", ['expected_version' => 1])
            ->assertOk();

        $startsAt = $linkedFocus->starts_at->utc()->format('Y-m-d\TH:i:s\Z');
        $endsAt = $linkedFocus->ends_at->utc()->format('Y-m-d\TH:i:s\Z');
        $this->withHeaders($this->headers())
            ->putJson("/api/v1/focus-sessions/{$linkedFocus->public_id}", [
                'expected_version' => 1,
                'task_id' => $task->public_id,
                'course_id' => null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'note' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 1);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/focus-sessions/{$unlinkedFocus->public_id}", [
                'expected_version' => 1,
                'task_id' => $task->public_id,
                'course_id' => null,
                'starts_at' => $unlinkedFocus->starts_at->utc()->format('Y-m-d\TH:i:s\Z'),
                'ends_at' => $unlinkedFocus->ends_at->utc()->format('Y-m-d\TH:i:s\Z'),
                'note' => null,
            ])
            ->assertConflict();
    }

    public function test_planner_inputs_reject_unknown_fields_unsafe_text_invalid_targets_and_time_ranges(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->getKey()]);
        $task = Task::factory()->forCourse($course)->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/tasks', [
                'title' => '<script>private</script>',
                'due_at' => '2026-07-15 12:00:00',
                'owner_id' => 123,
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['title', 'due_at', 'owner_id']]]]);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/focus-sessions', [
                'task_id' => $task->public_id,
                'course_id' => $course->public_id,
                'starts_at' => '2026-07-15T10:00:00Z',
                'ends_at' => '2026-07-16T10:00:01Z',
                'note' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['course_id', 'ends_at']]]]);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/planner/agenda?timezone=GMT%2B6&date=2026-02-30')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['timezone', 'date']]]]);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/planner/agenda?timezone=Pacific%2FApia&date=2011-12-30')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['date']]]]);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/planner/weekly?timezone=UTC&week_start=9999-12-31')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['week_start']]]]);
    }

    public function test_planner_database_failures_do_not_disclose_private_values(): void
    {
        $privateValue = 'private planner title that must never reach logs';
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        DB::statement(<<<'SQL'
            ALTER TABLE tasks
            ADD CONSTRAINT tasks_synthetic_private_failure
            CHECK (title <> 'private planner title that must never reach logs')
            SQL);
        Log::spy();

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/tasks', [
                'title' => $privateValue,
                'description' => null,
                'course_id' => null,
                'due_at' => null,
            ])
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'INTERNAL_ERROR');

        $this->assertStringNotContainsString($privateValue, (string) $response->getContent());
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Planner persistence failed.', Mockery::on(
                static fn (array $context): bool => $context['operation'] === 'task.create'
                    && $context['sql_state'] === '23514'
                    && $context['exception_type'] === QueryException::class
                    && is_string($context['request_id'])
                    && ! str_contains(serialize($context), $privateValue),
            ));
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'planner-lifecycle-test-token',
        ];
    }
}
