<?php

declare(strict_types=1);

namespace Tests\Feature\SecondBrain;

use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\SecondBrain\Concerns\InteractsWithSecondBrain;
use Tests\TestCase;

/**
 * The "Saved" bookmark is stored on the owned row (knowledge_items.saved_at),
 * so it behaves like every other owned mutation: the owner flips it, the flag
 * round-trips, saving is idempotent in both directions, the saved-only listing
 * is a server-side filter, and a stranger can neither save nor unsave a row
 * that is not theirs.
 */
final class KnowledgeBookmarkTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithSecondBrain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_saving_then_unsaving_flips_the_saved_flag(): void
    {
        $user = User::factory()->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create(['title' => 'Lecture notes']);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/knowledge/{$item->public_id}")
            ->assertOk()
            ->assertJsonPath('data.saved', false);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.saved', true);

        self::assertNotNull(
            DB::table('knowledge_items')->where('id', $item->getKey())->value('saved_at'),
        );

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/knowledge/{$item->public_id}/saved")
            ->assertNoContent();

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/knowledge/{$item->public_id}")
            ->assertOk()
            ->assertJsonPath('data.saved', false);

        self::assertNull(
            DB::table('knowledge_items')->where('id', $item->getKey())->value('saved_at'),
        );
    }

    public function test_saving_an_already_saved_item_is_a_no_op_that_keeps_the_original_bookmark(): void
    {
        $user = User::factory()->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.saved', true);

        // Backdate the bookmark, then save again: an idempotent save must not
        // move the timestamp forward.
        $backdated = now('UTC')->subDays(3)->startOfSecond();
        DB::table('knowledge_items')->where('id', $item->getKey())->update(['saved_at' => $backdated]);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$item->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.saved', true);

        $stored = DB::table('knowledge_items')->where('id', $item->getKey())->value('saved_at');
        self::assertIsString($stored);
        self::assertSame(
            $backdated->format('Y-m-d H:i:s'),
            Carbon::parse($stored)->utc()->format('Y-m-d H:i:s'),
        );
    }

    public function test_unsaving_an_item_that_was_never_saved_is_a_no_op_success(): void
    {
        $user = User::factory()->create();
        $item = KnowledgeItem::factory()->for($user, 'user')->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/knowledge/{$item->public_id}/saved")
            ->assertNoContent();

        self::assertNull(
            DB::table('knowledge_items')->where('id', $item->getKey())->value('saved_at'),
        );
    }

    public function test_the_saved_filter_returns_only_saved_items(): void
    {
        $user = User::factory()->create();
        $saved = KnowledgeItem::factory()->for($user, 'user')->create(['title' => 'Kept for later']);
        KnowledgeItem::factory()->for($user, 'user')->create(['title' => 'Just browsing']);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/knowledge/{$saved->public_id}/saved")
            ->assertOk();

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/knowledge?saved=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Kept for later')
            ->assertJsonPath('data.0.saved', true);

        // Without the filter the whole library is visible.
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/knowledge')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_stranger_can_neither_save_nor_unsave_another_users_item(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $item = KnowledgeItem::factory()->for($owner, 'user')->create();
        $this->actingAs($intruder, 'web');

        $this->assertApiError(
            $this->withHeaders($this->headers())->putJson("/api/v1/knowledge/{$item->public_id}/saved"),
            404,
            ApiErrorCode::ResourceNotFound,
        );

        $this->assertApiError(
            $this->withHeaders($this->headers())->deleteJson("/api/v1/knowledge/{$item->public_id}/saved"),
            404,
            ApiErrorCode::ResourceNotFound,
        );

        self::assertNull(
            DB::table('knowledge_items')->where('id', $item->getKey())->value('saved_at'),
            'A rejected request must not have written a bookmark.',
        );
    }
}
