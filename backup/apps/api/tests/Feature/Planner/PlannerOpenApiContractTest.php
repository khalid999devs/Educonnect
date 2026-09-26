<?php

declare(strict_types=1);

namespace Tests\Feature\Planner;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

final class PlannerOpenApiContractTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

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

    public function test_every_planner_operation_matches_the_live_openapi_contract(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $task = $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/tasks', [
                'title' => 'Contract Task',
                'description' => 'A contract-validated deadline.',
                'due_at' => '2026-07-14T10:00:00+06:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.archive_status', 'active');
        $taskId = $task->json('data.id');
        $this->assertIsString($taskId);

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('data.id', $taskId);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/tasks/{$taskId}", [
                'expected_version' => 1,
                'title' => 'Updated Contract Task',
                'description' => null,
                'due_at' => '2026-07-14T10:30:00+06:00',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/tasks/{$taskId}/status", [
                'expected_version' => 2,
                'status' => 'completed',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.status', 'completed');

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/tasks/{$taskId}/archive", ['expected_version' => 3])
            ->assertOk()
            ->assertJsonPath('data.version', 4)
            ->assertJsonPath('data.archive_status', 'archived');

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/tasks/{$taskId}/archive", ['expected_version' => 4])
            ->assertOk()
            ->assertJsonPath('data.version', 5)
            ->assertJsonPath('data.archive_status', 'active');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/tasks?status=completed&archive_status=active&has_due=true'
                .'&due_from=2026-07-14T00%3A00%3A00%2B06%3A00'
                .'&due_before=2026-07-15T00%3A00%3A00%2B06%3A00&sort=updated_at&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $focus = $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/focus-sessions', [
                'task_id' => $taskId,
                'starts_at' => '2026-07-14T09:00:00+06:00',
                'ends_at' => '2026-07-14T10:00:00+06:00',
                'note' => 'Focused contract session.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.task.id', $taskId)
            ->assertJsonPath('data.course', null);
        $focusId = $focus->json('data.id');
        $this->assertIsString($focusId);

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/focus-sessions/{$focusId}")
            ->assertOk()
            ->assertJsonPath('data.id', $focusId);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/focus-sessions/{$focusId}", [
                'expected_version' => 1,
                'task_id' => $taskId,
                'starts_at' => '2026-07-14T09:15:00+06:00',
                'ends_at' => '2026-07-14T10:00:00+06:00',
                'note' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/focus-sessions?task_id='.$taskId
                .'&overlap_from=2026-07-14T00%3A00%3A00%2B06%3A00'
                .'&overlap_before=2026-07-15T00%3A00%3A00%2B06%3A00&sort=-starts_at&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/planner/agenda?timezone=Asia%2FDhaka&date=2026-07-14&limit=1')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Dhaka')
            ->assertJsonPath('data.date', '2026-07-14')
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonCount(1, 'data.focus_sessions')
            ->assertJsonPath('meta.limit', 1);

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/planner/weekly?timezone=Asia%2FDhaka&week_start=2026-07-13&limit=1')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Dhaka')
            ->assertJsonPath('data.week_start', '2026-07-13')
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonCount(1, 'data.focus_sessions');

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/tasks/{$taskId}", ['expected_version' => 5])
            ->assertConflict()
            ->assertJsonPath('error.code', 'CONFLICT');

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/focus-sessions/{$focusId}", ['expected_version' => 2])
            ->assertNoContent();

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/tasks/{$taskId}", ['expected_version' => 5])
            ->assertNoContent();

        $this->withoutRequestValidation()
            ->withHeaders($this->readHeaders())
            ->getJson('/api/v1/planner/agenda?timezone=Not%2FAZone&date=2026-07-14')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /** @return array<string, string> */
    private function mutationHeaders(): array
    {
        return [
            ...$this->readHeaders(),
            'X-XSRF-TOKEN' => 'planner-contract-test-token',
        ];
    }

    /** @return array<string, string> */
    private function readHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'contract-session');

        return $authenticatedRequest;
    }
}
