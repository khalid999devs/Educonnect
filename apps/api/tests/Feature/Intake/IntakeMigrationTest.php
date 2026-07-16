<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Domains\Intake\Models\IntakeArtifact;
use App\Domains\Intake\Models\IntakeEvent;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class IntakeMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_state_transitions_and_identity_are_database_enforced(): void
    {
        $item = IntakeItem::factory()->create();

        // uploaded_or_linked cannot jump straight to extracted.
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_items')
            ->where('id', $item->getKey())
            ->update(['state' => 'extracted']));

        DB::table('intake_items')->where('id', $item->getKey())->update(['state' => 'queued']);
        DB::table('intake_items')->where('id', $item->getKey())->update(['state' => 'extracting']);
        DB::table('intake_items')->where('id', $item->getKey())->update(['state' => 'extracted']);

        // extracted has no edge back to queued.
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_items')
            ->where('id', $item->getKey())
            ->update(['state' => 'queued']));

        $this->assertQueryRejected('23514', static fn () => DB::table('intake_items')
            ->where('id', $item->getKey())
            ->update(['url' => 'https://changed.example.edu/other']));
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_items')
            ->where('id', $item->getKey())
            ->update(['user_id' => 999_999]));

        // Failure codes are only legal in failure states.
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_items')
            ->where('id', $item->getKey())
            ->update(['failure_code' => 'link_fetch_failed']));
    }

    public function test_source_consistency_artifact_immutability_and_event_append_only_hold(): void
    {
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_items')->insert([
            'public_id' => strtolower('01'.substr(str_repeat('abcdefgh', 4), 0, 24)),
            'user_id' => User::factory()->create()->getKey(),
            'source_type' => 'link',
            'url' => null,
            'state' => 'uploaded_or_linked',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $artifact = IntakeArtifact::factory()->create();
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_artifacts')
            ->where('id', $artifact->getKey())
            ->update(['text_content' => 'tampered']));

        $event = IntakeEvent::factory()->create();
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_events')
            ->where('id', $event->getKey())
            ->update(['detail' => 'rewritten history']));
    }

    public function test_referential_actions_protect_resources_and_cascade_with_users(): void
    {
        $user = User::factory()->create();
        $resource = Resource::factory()->file()->create(['user_id' => $user->getKey()]);
        $item = IntakeItem::factory()->create([
            'user_id' => $user->getKey(),
            'source_type' => 'file',
            'resource_id' => $resource->getKey(),
            'url' => null,
        ]);
        IntakeArtifact::factory()->create(['intake_item_id' => $item->getKey()]);
        IntakeEvent::factory()->create(['intake_item_id' => $item->getKey()]);

        // The referenced resource refuses deletion while the intake item exists.
        $this->assertQueryRejected('23503', static fn () => DB::table('resources')
            ->where('id', $resource->getKey())
            ->delete());

        // Deleting a user cascades their intake items with artifacts and events.
        $linkOwner = User::factory()->create();
        $linkItem = IntakeItem::factory()->create(['user_id' => $linkOwner->getKey()]);
        IntakeArtifact::factory()->create(['intake_item_id' => $linkItem->getKey()]);
        IntakeEvent::factory()->create(['intake_item_id' => $linkItem->getKey()]);

        DB::table('users')->where('id', $linkOwner->getKey())->delete();
        $this->assertDatabaseMissing('intake_items', ['id' => $linkItem->getKey()]);
        $this->assertDatabaseMissing('intake_artifacts', ['intake_item_id' => $linkItem->getKey()]);
        $this->assertDatabaseMissing('intake_events', ['intake_item_id' => $linkItem->getKey()]);
        $this->assertDatabaseHas('intake_items', ['id' => $item->getKey()]);
    }

    public function test_migration_rolls_back_atomically_when_empty_and_refuses_intake_rows(): void
    {
        // Phase 15 suggestions reference intake items, so they lower first.
        $suggestions = require database_path('migrations/2026_07_16_000014_create_intake_suggestions.php');
        self::assertInstanceOf(Migration::class, $suggestions);
        $suggestions->down();

        $migration = $this->migration();

        $migration->down();
        self::assertFalse(Schema::hasTable('intake_items'));
        self::assertFalse(Schema::hasTable('intake_artifacts'));
        self::assertFalse(Schema::hasTable('intake_events'));

        $migration->up();
        self::assertTrue(Schema::hasTable('intake_items'));
        self::assertTrue(Schema::hasTable('intake_artifacts'));
        self::assertTrue(Schema::hasTable('intake_events'));

        $item = IntakeItem::factory()->create();

        try {
            $migration->down();
            self::fail('The intake migration erased private intake data.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('private intake data exists', $exception->getMessage());
        }

        self::assertTrue(Schema::hasTable('intake_items'));
        $this->assertDatabaseHas('intake_items', ['id' => $item->getKey()]);
    }

    private function migration(): Migration
    {
        $migration = require database_path(
            'migrations/2026_07_16_000013_create_intake_foundation.php',
        );
        self::assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    /** @param callable(): mixed $operation */
    private function assertQueryRejected(string $expectedSqlState, callable $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            self::assertSame($expectedSqlState, $exception->errorInfo[0] ?? null);

            return;
        }

        self::fail("Expected PostgreSQL to reject the statement with SQLSTATE {$expectedSqlState}.");
    }
}
