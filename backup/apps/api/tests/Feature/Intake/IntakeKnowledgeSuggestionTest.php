<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Domains\Intake\AI\ClassificationRequest;
use App\Domains\Intake\AI\SuggestionSchemaV1;
use App\Domains\Intake\AI\SuggestionSchemaV2;
use App\Domains\Intake\Exceptions\InvalidSuggestionOutput;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Models\IntakeSuggestion;
use App\Domains\Planner\Models\Task;
use App\Domains\Resources\Models\Resource;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Intake\Concerns\InteractsWithIntake;
use Tests\TestCase;

/**
 * Confirming a knowledge suggestion is the third, newest branch of
 * ConfirmIntakeAction. These tests exist mainly to pin the branch itself: the
 * dangerous failure mode is not an exception, it is a knowledge suggestion
 * quietly becoming a resource.
 */
final class IntakeKnowledgeSuggestionTest extends TestCase
{
    use InteractsWithIntake;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_confirming_a_knowledge_suggestion_creates_a_knowledge_item_with_provenance(): void
    {
        $user = User::factory()->create();
        $item = IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);
        $suggestion = IntakeSuggestion::factory()->knowledgeItem()->create(['intake_item_id' => $item->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $suggestion->public_id, 'action' => 'apply'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.state', 'saved');

        self::assertSame(1, KnowledgeItem::query()->count());
        $knowledgeItem = KnowledgeItem::query()->firstOrFail();
        self::assertSame('Spectral methods for boundary value problems', $knowledgeItem->title);
        self::assertSame('A survey chapter the seminar reading list points at.', $knowledgeItem->summary);
        self::assertSame('link', $knowledgeItem->source_type);
        self::assertSame('https://intake.example.edu/spectral-methods.pdf', $knowledgeItem->source_url);
        self::assertSame((int) $user->getKey(), (int) $knowledgeItem->user_id);
        self::assertSame((int) $item->getKey(), (int) $knowledgeItem->intake_item_id);

        $suggestion->refresh();
        self::assertSame('applied', $suggestion->status->value);
        self::assertSame($knowledgeItem->getKey(), $suggestion->created_knowledge_item_id);
        self::assertNull($suggestion->created_task_id);
        self::assertNull($suggestion->created_resource_id);
    }

    /**
     * The regression guard for the old "not a task means a resource" branch:
     * under it this exact confirmation produced a Resource and left
     * created_knowledge_item_id null, with no error anywhere.
     */
    public function test_a_knowledge_suggestion_is_never_misrouted_into_a_resource(): void
    {
        $user = User::factory()->create();
        $item = IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);
        $suggestion = IntakeSuggestion::factory()->knowledgeItem()->create(['intake_item_id' => $item->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $suggestion->public_id, 'action' => 'apply'],
                ],
            ])
            ->assertOk();

        self::assertSame(0, Resource::query()->count());
        self::assertSame(0, Task::query()->count());
        self::assertSame(1, KnowledgeItem::query()->count());
        self::assertNotNull($suggestion->refresh()->created_knowledge_item_id);
    }

    public function test_a_urlless_knowledge_suggestion_is_stored_without_a_source(): void
    {
        $user = User::factory()->create();
        $item = IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);
        $suggestion = IntakeSuggestion::factory()->knowledgeItem(null)->create(['intake_item_id' => $item->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $suggestion->public_id, 'action' => 'apply'],
                ],
            ])
            ->assertOk();

        $knowledgeItem = KnowledgeItem::query()->firstOrFail();
        self::assertSame('none', $knowledgeItem->source_type);
        self::assertNull($knowledgeItem->source_url);
    }

    public function test_reconfirming_a_knowledge_suggestion_creates_nothing_further(): void
    {
        $user = User::factory()->create();
        $item = IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);
        $suggestion = IntakeSuggestion::factory()->knowledgeItem()->create(['intake_item_id' => $item->getKey()]);
        $this->actingAs($user, 'web');

        $payload = [
            'decisions' => [
                ['id' => $suggestion->public_id, 'action' => 'apply'],
            ],
        ];

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", $payload)
            ->assertOk();

        $created = KnowledgeItem::query()->firstOrFail();

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", $payload)
            ->assertOk()
            ->assertJsonPath('data.state', 'saved');

        self::assertSame(1, KnowledgeItem::query()->count());
        self::assertSame($created->getKey(), $suggestion->refresh()->created_knowledge_item_id);
    }

    public function test_undecided_knowledge_proposals_are_still_force_dismissed(): void
    {
        $user = User::factory()->create();
        $item = IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);
        $applied = IntakeSuggestion::factory()->knowledgeItem()->create(['intake_item_id' => $item->getKey()]);
        $undecided = IntakeSuggestion::factory()->knowledgeItem()->create(['intake_item_id' => $item->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $applied->public_id, 'action' => 'apply'],
                ],
            ])
            ->assertOk();

        self::assertSame('applied', $applied->refresh()->status->value);
        self::assertSame('dismissed', $undecided->refresh()->status->value);
        self::assertNull($undecided->created_knowledge_item_id);
        self::assertSame(1, KnowledgeItem::query()->count());
    }

    /**
     * schema_version is persisted per row, so rows written before v2 existed
     * must keep confirming exactly as they always did.
     */
    public function test_a_v1_row_still_confirms_with_its_original_meaning(): void
    {
        $user = User::factory()->create();
        $item = IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);
        $task = IntakeSuggestion::factory()->create([
            'intake_item_id' => $item->getKey(),
            'schema_version' => 'v1',
        ]);
        $resource = IntakeSuggestion::factory()->resource()->create([
            'intake_item_id' => $item->getKey(),
            'schema_version' => 'v1',
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/intake/{$item->public_id}/suggestions")
            ->assertOk()
            ->assertJsonPath('data.0.schema_version', 'v1');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $task->public_id, 'action' => 'apply'],
                    ['id' => $resource->public_id, 'action' => 'apply'],
                ],
            ])
            ->assertOk();

        self::assertSame(1, Task::query()->count());
        self::assertSame(1, Resource::query()->count());
        self::assertSame(0, KnowledgeItem::query()->count());
        self::assertSame('v1', $task->refresh()->schema_version);
    }

    public function test_a_foreign_user_cannot_confirm_a_knowledge_suggestion_or_reach_its_knowledge_item(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $item = IntakeItem::factory()->awaitingReview()->create(['user_id' => $owner->getKey()]);
        $suggestion = IntakeSuggestion::factory()->knowledgeItem()->create(['intake_item_id' => $item->getKey()]);

        $this->actingAs($intruder, 'web');
        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $suggestion->public_id, 'action' => 'apply'],
                ],
            ])
            ->assertNotFound();

        self::assertSame(0, KnowledgeItem::query()->count());
        self::assertSame('proposed', $suggestion->refresh()->status->value);

        $this->app['auth']->forgetGuards();
        $this->actingAs($owner, 'web');
        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $suggestion->public_id, 'action' => 'apply'],
                ],
            ])
            ->assertOk();

        $knowledgeItem = KnowledgeItem::query()->firstOrFail();
        self::assertSame((int) $owner->getKey(), (int) $knowledgeItem->user_id);

        $this->app['auth']->forgetGuards();
        $this->actingAs($intruder, 'web');
        $this->withHeaders($this->headers())
            ->getJson("/api/v1/knowledge/{$knowledgeItem->public_id}")
            ->assertNotFound();
    }

    public function test_schema_v2_accepts_the_knowledge_kind_while_v1_keeps_rejecting_it(): void
    {
        $request = new ClassificationRequest(
            extractedText: 'Reading list',
            context: null,
            sourceUrl: null,
            courses: [],
            maxSuggestions: 5,
        );
        $raw = [
            'suggestions' => [
                [
                    'kind' => 'knowledge_item',
                    'title' => 'Spectral methods',
                    'description' => null,
                    'due_at' => null,
                    'course_public_id' => null,
                    'url' => 'https://intake.example.edu/spectral-methods.pdf',
                    'confidence' => 0.6,
                    'reason' => 'The reading list names this chapter.',
                ],
            ],
        ];

        self::assertSame('v2', SuggestionSchemaV2::VERSION);
        self::assertSame('v1', SuggestionSchemaV1::VERSION);

        $validated = (new SuggestionSchemaV2)->validate($raw, $request);
        self::assertSame('knowledge_item', $validated[0]['kind']);

        $this->expectException(InvalidSuggestionOutput::class);
        (new SuggestionSchemaV1)->validate($raw, $request);
    }

    public function test_schema_v2_rejects_a_due_date_on_a_knowledge_suggestion(): void
    {
        $request = new ClassificationRequest(
            extractedText: 'Reading list',
            context: null,
            sourceUrl: null,
            courses: [],
            maxSuggestions: 5,
        );

        $this->expectException(InvalidSuggestionOutput::class);

        (new SuggestionSchemaV2)->validate([
            'suggestions' => [
                [
                    'kind' => 'knowledge_item',
                    'title' => 'Spectral methods',
                    'due_at' => '2026-09-01',
                    'confidence' => 0.6,
                    'reason' => 'The reading list names this chapter.',
                ],
            ],
        ], $request);
    }
}
