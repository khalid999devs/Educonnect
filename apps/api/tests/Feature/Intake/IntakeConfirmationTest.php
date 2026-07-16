<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Domains\Courses\Models\Course;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Models\IntakeSuggestion;
use App\Domains\Planner\Models\Task;
use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Intake\Concerns\InteractsWithIntake;
use Tests\TestCase;

final class IntakeConfirmationTest extends TestCase
{
    use InteractsWithIntake;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_confirmed_suggestions_create_records_transactionally_and_retries_never_duplicate(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->getKey()]);
        $item = IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);
        $taskSuggestion = IntakeSuggestion::factory()->create(['intake_item_id' => $item->getKey()]);
        $resourceSuggestion = IntakeSuggestion::factory()->resource()->create(['intake_item_id' => $item->getKey()]);
        $undecided = IntakeSuggestion::factory()->create(['intake_item_id' => $item->getKey()]);
        $this->actingAs($user, 'web');

        self::assertSame(0, Task::query()->count());
        self::assertSame(0, Resource::query()->count());

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/intake/{$item->public_id}/suggestions")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.status', 'proposed')
            ->assertJsonPath('data.0.reason', 'The document mentions an assignment next to a due date.');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    [
                        'id' => $taskSuggestion->public_id,
                        'action' => 'apply',
                        'overrides' => [
                            'title' => 'Submit the methods assignment (edited)',
                            'course_id' => $course->public_id,
                            'due_at' => '2026-08-05',
                        ],
                    ],
                    ['id' => $resourceSuggestion->public_id, 'action' => 'apply'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.state', 'saved');

        self::assertSame(1, Task::query()->count());
        self::assertSame(1, Resource::query()->count());
        $task = Task::query()->firstOrFail();
        self::assertSame('Submit the methods assignment (edited)', $task->title);
        self::assertSame($course->getKey(), $task->course_id);
        $resource = Resource::query()->firstOrFail();
        self::assertSame('https://intake.example.edu/reading-list', $resource->source_url);

        self::assertSame('applied', $taskSuggestion->refresh()->status->value);
        self::assertSame($task->getKey(), $taskSuggestion->created_task_id);
        self::assertSame('applied', $resourceSuggestion->refresh()->status->value);
        self::assertSame($resource->getKey(), $resourceSuggestion->created_resource_id);
        self::assertSame('dismissed', $undecided->refresh()->status->value);

        // Retrying the exact confirmation is an idempotent no-op.
        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $taskSuggestion->public_id, 'action' => 'apply'],
                    ['id' => $resourceSuggestion->public_id, 'action' => 'apply'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.state', 'saved');
        self::assertSame(1, Task::query()->count());
        self::assertSame(1, Resource::query()->count());
    }

    public function test_dismissing_everything_saves_the_item_without_creating_any_record(): void
    {
        $user = User::factory()->create();
        $item = IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);
        $suggestion = IntakeSuggestion::factory()->create(['intake_item_id' => $item->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $suggestion->public_id, 'action' => 'dismiss'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.state', 'saved');

        self::assertSame(0, Task::query()->count());
        self::assertSame(0, Resource::query()->count());
        self::assertSame('dismissed', $suggestion->refresh()->status->value);
    }

    public function test_confirmation_guards_state_ownership_and_payload_integrity(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $item = IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);
        $suggestion = IntakeSuggestion::factory()->create(['intake_item_id' => $item->getKey()]);
        $queued = IntakeItem::factory()->queued()->create(['user_id' => $user->getKey()]);
        $foreignItem = IntakeItem::factory()->awaitingReview()->create(['user_id' => $other->getKey()]);
        $foreignSuggestion = IntakeSuggestion::factory()->create(['intake_item_id' => $foreignItem->getKey()]);
        $archivedCourse = Course::factory()->create([
            'user_id' => $user->getKey(),
            'archived_at' => now(),
        ]);
        $urllessResource = IntakeSuggestion::factory()->resource(null)->create(['intake_item_id' => $item->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$queued->public_id}/confirmation", ['decisions' => []])
            ->assertStatus(409);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$foreignItem->public_id}/confirmation", ['decisions' => []])
            ->assertNotFound();

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $foreignSuggestion->public_id, 'action' => 'apply'],
                ],
            ])
            ->assertUnprocessable();

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $urllessResource->public_id, 'action' => 'apply'],
                ],
            ])
            ->assertUnprocessable();

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    [
                        'id' => $suggestion->public_id,
                        'action' => 'apply',
                        'overrides' => ['course_id' => $archivedCourse->public_id],
                    ],
                ],
            ])
            ->assertStatus(409);

        // Every failed attempt above left the review fully recoverable.
        self::assertSame('awaiting_review', $item->refresh()->state->value);
        self::assertSame('proposed', $suggestion->refresh()->status->value);
        self::assertSame(0, Task::query()->count());
        self::assertSame(0, Resource::query()->count());

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $suggestion->public_id, 'action' => 'apply', 'unexpected' => true],
                ],
            ])
            ->assertUnprocessable();
    }
}
