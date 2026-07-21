<?php

declare(strict_types=1);

namespace Tests\Feature\SecondBrain;

use App\Domains\Intake\Models\IntakeItem;
use App\Domains\SecondBrain\Actions\CreateKnowledgeItemAction;
use App\Domains\SecondBrain\Enums\KnowledgePurpose;
use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\SecondBrain\Concerns\InteractsWithSecondBrain;
use Tests\TestCase;

/**
 * Purpose is the organizing principle of the Second Brain, so it has to behave
 * like a first-class column: it round-trips, it filters, an unknown value is
 * refused rather than coerced, and the rows written before the column existed
 * keep reading back as "no purpose recorded".
 */
final class KnowledgePurposeTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithSecondBrain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_purpose_round_trips_from_create_through_read_and_filter(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $itemId = $this->withHeaders($this->headers())
            ->postJson('/api/v1/knowledge', [
                'title' => 'Discrete maths problem set',
                'source_type' => 'none',
                'purpose' => 'exam',
            ])
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'exam')
            ->json('data.id');
        $this->assertIsString($itemId);

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/knowledge/{$itemId}")
            ->assertOk()
            ->assertJsonPath('data.purpose', 'exam');

        KnowledgeItem::factory()->for($user, 'user')->create([
            'title' => 'Lecture six notes',
            'purpose' => KnowledgePurpose::Study->value,
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/knowledge?purpose=exam')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Discrete maths problem set');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/knowledge?purpose=study')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Lecture six notes');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/knowledge?purpose=research')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_owner_can_set_and_clear_purpose_while_an_edit_without_the_key_preserves_it(): void
    {
        $user = User::factory()->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create([
            'title' => 'Thermodynamics primer',
            'purpose' => KnowledgePurpose::Study->value,
        ]);
        $this->actingAs($user, 'web');

        // An edit form with no purpose control must not erase the stored value.
        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}", [
                'title' => 'Thermodynamics primer, revised',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.purpose', 'study')
            ->assertJsonPath('data.version', 2);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}", [
                'title' => 'Thermodynamics primer, revised',
                'purpose' => 'exam',
                'expected_version' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.purpose', 'exam')
            ->assertJsonPath('data.version', 3);

        // An explicit null is a deliberate clear, unlike an absent key.
        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}", [
                'title' => 'Thermodynamics primer, revised',
                'purpose' => null,
                'expected_version' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.purpose', null)
            ->assertJsonPath('data.version', 4);
    }

    public function test_unknown_purpose_is_rejected_on_every_surface(): void
    {
        $user = User::factory()->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create();
        $this->actingAs($user, 'web');

        $this->assertApiError(
            $this->withHeaders($this->headers())->postJson('/api/v1/knowledge', [
                'title' => 'Bad purpose',
                'source_type' => 'none',
                'purpose' => 'revision',
            ]),
            422,
            ApiErrorCode::ValidationFailed,
        );

        $this->assertApiError(
            $this->withHeaders($this->headers())->putJson("/api/v1/knowledge/{$item->public_id}", [
                'title' => 'Bad purpose',
                'purpose' => 'revision',
                'expected_version' => 1,
            ]),
            422,
            ApiErrorCode::ValidationFailed,
        );

        $this->assertApiError(
            $this->withHeaders($this->headers())->getJson('/api/v1/knowledge?purpose=revision'),
            422,
            ApiErrorCode::ValidationFailed,
        );

        self::assertNull(
            $item->refresh()->purpose,
            'A rejected request must not have written anything.',
        );
    }

    public function test_rows_written_before_the_column_existed_read_back_as_null(): void
    {
        $user = User::factory()->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create(['title' => 'Legacy capture']);
        DB::table('knowledge_items')->where('id', $item->getKey())->update(['purpose' => null]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/knowledge/{$item->public_id}")
            ->assertOk()
            ->assertJsonPath('data.purpose', null);

        // The backlog is addressable without pretending it has a purpose.
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/knowledge?purpose=none')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Legacy capture');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/knowledge?purpose=resource')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_another_users_purposed_item_is_never_readable_or_filterable(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $foreign = KnowledgeItem::factory()->for($owner, 'user')->create([
            'title' => 'Owner exam plan',
            'purpose' => KnowledgePurpose::Exam->value,
        ]);
        $this->actingAs($intruder, 'web');

        $this->assertApiError(
            $this->withHeaders($this->headers())->getJson("/api/v1/knowledge/{$foreign->public_id}"),
            404,
            ApiErrorCode::ResourceNotFound,
        );

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/knowledge?purpose=exam')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_provenance_cannot_be_claimed_over_http(): void
    {
        $user = User::factory()->create();
        $intake = IntakeItem::factory()->for($user, 'user')->create();
        $this->actingAs($user, 'web');

        $this->assertApiError(
            $this->withHeaders($this->headers())->postJson('/api/v1/knowledge', [
                'title' => 'Forged provenance',
                'source_type' => 'none',
                'intake_item_id' => $intake->public_id,
            ]),
            422,
            ApiErrorCode::ValidationFailed,
        );
    }

    public function test_composite_foreign_key_rejects_cross_user_provenance(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $foreignCapture = IntakeItem::factory()->for($stranger, 'user')->create();
        $action = app(CreateKnowledgeItemAction::class);

        $this->expectException(BrainPersistenceFailure::class);

        $action->execute($owner, [
            'title' => 'Linked to someone else capture',
            'summary' => null,
            'source_type' => 'none',
            'resource_id' => null,
            'source_url' => null,
            'authors' => null,
            'published_year' => null,
            'venue' => null,
            'doi' => null,
            'purpose' => KnowledgePurpose::Study->value,
        ], (int) $foreignCapture->getKey());
    }

    public function test_the_action_stamps_provenance_for_the_owners_own_capture(): void
    {
        $user = User::factory()->create();
        $capture = IntakeItem::factory()->for($user, 'user')->create();

        $item = app(CreateKnowledgeItemAction::class)->execute($user, [
            'title' => 'Extracted from my own capture',
            'summary' => null,
            'source_type' => 'none',
            'resource_id' => null,
            'source_url' => null,
            'authors' => null,
            'published_year' => null,
            'venue' => null,
            'doi' => null,
            'purpose' => KnowledgePurpose::Research->value,
        ], (int) $capture->getKey());

        self::assertSame(KnowledgePurpose::Research, $item->purpose);
        self::assertSame((int) $capture->getKey(), (int) $item->getAttribute('intake_item_id'));
        self::assertArrayNotHasKey(
            'intake_item_id',
            $item->toArray(),
            'Internal provenance ids stay hidden from serialization.',
        );
    }
}
