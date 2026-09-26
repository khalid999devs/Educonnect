<?php

declare(strict_types=1);

namespace Tests\Feature\Study;

use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Asserts migration `2026_07_20_000019_create_study_and_purpose_foundation`
 * directly against the live PostgreSQL schema.
 */
final class StudyMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_07_20_000019_create_study_and_purpose_foundation.php';

    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private const PPTX_MIME = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

    public function test_knowledge_item_purpose_is_nullable_and_check_constrained(): void
    {
        $item = KnowledgeItem::factory()->create();

        self::assertNull(DB::table('knowledge_items')->where('id', $item->getKey())->value('purpose'));

        foreach (['resource', 'study', 'research', 'exam'] as $purpose) {
            DB::table('knowledge_items')->where('id', $item->getKey())->update(['purpose' => $purpose]);
            self::assertSame($purpose, DB::table('knowledge_items')->where('id', $item->getKey())->value('purpose'));
        }

        $this->assertQueryRejected('23514', static fn () => DB::table('knowledge_items')
            ->where('id', $item->getKey())
            ->update(['purpose' => 'homework']));
    }

    public function test_knowledge_item_intake_provenance_is_tenant_scoped(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $item = KnowledgeItem::factory()->for($owner)->create();
        $ownIntake = IntakeItem::factory()->for($owner)->create();
        $foreignIntake = IntakeItem::factory()->for($intruder)->create();

        DB::table('knowledge_items')->where('id', $item->getKey())->update(['intake_item_id' => $ownIntake->getKey()]);
        self::assertSame(
            (int) $ownIntake->getKey(),
            (int) DB::table('knowledge_items')->where('id', $item->getKey())->value('intake_item_id'),
        );

        // The composite FK makes a cross-tenant reference impossible.
        $this->assertQueryRejected('23503', static fn () => DB::table('knowledge_items')
            ->where('id', $item->getKey())
            ->update(['intake_item_id' => $foreignIntake->getKey()]));
    }

    public function test_intake_items_carries_the_owner_unique_the_composite_foreign_key_requires(): void
    {
        self::assertTrue($this->constraintExists('intake_items', 'intake_items_owner_id_unique'));
        self::assertTrue($this->constraintExists('knowledge_items', 'knowledge_items_owner_intake_item_foreign'));
        self::assertTrue($this->indexExists('knowledge_items_owner_intake_item_idx'));
    }

    public function test_intake_suggestion_kind_accepts_knowledge_item_and_records_provenance(): void
    {
        $owner = User::factory()->create();
        $intake = IntakeItem::factory()->for($owner)->create();
        $knowledgeItem = KnowledgeItem::factory()->for($owner)->create();

        $suggestionId = DB::table('intake_suggestions')->insertGetId([
            'intake_item_id' => $intake->getKey(),
            'kind' => 'knowledge_item',
            'payload' => json_encode(['title' => 'Attention is all you need']),
            'schema_version' => 'v2',
            'confidence' => '0.900',
            'reason' => 'The document reads as a reference paper worth keeping.',
            'status' => 'proposed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Provenance is only legal once the suggestion is applied.
        $this->assertQueryRejected('23514', static fn () => DB::table('intake_suggestions')
            ->where('id', $suggestionId)
            ->update(['created_knowledge_item_id' => $knowledgeItem->getKey()]));

        DB::table('intake_suggestions')->where('id', $suggestionId)->update([
            'status' => 'applied',
            'created_knowledge_item_id' => $knowledgeItem->getKey(),
        ]);

        // Deleting the created item clears provenance instead of blocking.
        DB::table('knowledge_items')->where('id', $knowledgeItem->getKey())->delete();
        self::assertNull(
            DB::table('intake_suggestions')->where('id', $suggestionId)->value('created_knowledge_item_id'),
        );

        $this->assertQueryRejected('23514', static fn () => DB::table('intake_suggestions')
            ->where('id', $suggestionId)
            ->update(['kind' => 'flashcard']));
    }

    public function test_study_artifact_kind_and_status_are_check_constrained(): void
    {
        $item = KnowledgeItem::factory()->create();

        $this->assertQueryRejected('23514', fn () => $this->insertArtifact($item, ['kind' => 'flashcards']));
        $this->assertQueryRejected('23514', fn () => $this->insertArtifact($item, ['status' => 'pending']));

        foreach (['summary', 'topic_explanation', 'quick_learn', 'exam_questions'] as $kind) {
            $id = $this->insertArtifact($item, ['kind' => $kind]);
            self::assertSame($kind, DB::table('study_artifacts')->where('id', $id)->value('kind'));
        }
    }

    public function test_study_artifact_public_id_defaults_to_a_generated_ulid(): void
    {
        $item = KnowledgeItem::factory()->create();
        $id = $this->insertArtifact($item);

        $publicId = DB::table('study_artifacts')->where('id', $id)->value('public_id');

        self::assertIsString($publicId);
        self::assertMatchesRegularExpression('/^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$/', $publicId);
    }

    public function test_study_artifact_is_unique_per_item_and_kind(): void
    {
        $item = KnowledgeItem::factory()->create();
        $this->insertArtifact($item, ['kind' => 'summary']);

        // The artifact row IS the dedupe/cache: one per (knowledge item, kind).
        $this->assertQueryRejected('23505', fn () => $this->insertArtifact($item, ['kind' => 'summary']));

        // A different kind for the same item is fine.
        $this->insertArtifact($item, ['kind' => 'quick_learn']);
        self::assertSame(2, DB::table('study_artifacts')->where('knowledge_item_id', $item->getKey())->count());
    }

    public function test_study_artifact_never_carries_fabricated_content_for_a_failed_generation(): void
    {
        $item = KnowledgeItem::factory()->create();

        // ready requires a payload.
        $this->assertQueryRejected('23514', fn () => $this->insertArtifact($item, [
            'status' => 'ready',
            'payload' => null,
        ]));

        // failed must NOT carry a payload - honest failure, never invented study material.
        $this->assertQueryRejected('23514', fn () => $this->insertArtifact($item, [
            'status' => 'failed',
            'payload' => json_encode(['summary' => 'invented']),
            'failure_reason' => 'The provider was unavailable.',
        ]));

        // A failure_reason is only legal on a failed artifact.
        $this->assertQueryRejected('23514', fn () => $this->insertArtifact($item, [
            'status' => 'queued',
            'failure_reason' => 'The provider was unavailable.',
        ]));

        $failedId = $this->insertArtifact($item, [
            'status' => 'failed',
            'failure_reason' => 'The provider was unavailable.',
        ]);
        self::assertNull(DB::table('study_artifacts')->where('id', $failedId)->value('payload'));
    }

    public function test_study_artifact_attribution_and_payload_shape_are_bounded(): void
    {
        $item = KnowledgeItem::factory()->create();

        $this->assertQueryRejected('23514', fn () => $this->insertArtifact($item, ['latency_ms' => -1]));
        $this->assertQueryRejected('23514', fn () => $this->insertArtifact($item, ['version' => 0]));
        $this->assertQueryRejected('23514', fn () => $this->insertArtifact($item, ['provider' => '  ']));
        $this->assertQueryRejected('23514', fn () => $this->insertArtifact($item, ['schema_version' => ' ']));
        $this->assertQueryRejected('23514', fn () => $this->insertArtifact($item, [
            'status' => 'ready',
            'payload' => json_encode(['a', 'b']),
        ]));
    }

    public function test_study_artifact_is_tenant_scoped_and_cascades_with_its_knowledge_item(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $item = KnowledgeItem::factory()->for($owner)->create();

        $this->assertQueryRejected('23503', function () use ($intruder, $item): void {
            DB::table('study_artifacts')->insert([
                'user_id' => $intruder->getKey(),
                'knowledge_item_id' => $item->getKey(),
                'kind' => 'summary',
                'status' => 'queued',
                'schema_version' => 'v1',
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $id = $this->insertArtifact($item);
        DB::table('knowledge_items')->where('id', $item->getKey())->delete();
        self::assertDatabaseMissing('study_artifacts', ['id' => $id]);
    }

    public function test_study_artifact_identity_is_immutable(): void
    {
        $item = KnowledgeItem::factory()->create();
        $id = $this->insertArtifact($item);

        $this->assertQueryRejected('23514', static fn () => DB::table('study_artifacts')
            ->where('id', $id)
            ->update(['public_id' => '01hzzzzzzzzzzzzzzzzzzzzzzz']));
        $this->assertQueryRejected('23514', static fn () => DB::table('study_artifacts')
            ->where('id', $id)
            ->update(['user_id' => User::factory()->create()->getKey()]));
    }

    public function test_study_artifact_cursor_and_status_indexes_exist(): void
    {
        self::assertTrue($this->indexExists('study_artifacts_owner_created_cursor_idx'));
        self::assertTrue($this->indexExists('study_artifacts_owner_status_idx'));
        self::assertTrue($this->indexExists('intake_suggestions_knowledge_item_lookup_idx'));
    }

    public function test_stored_file_mime_allowlists_accept_docx_and_pptx(): void
    {
        foreach ([self::DOCX_MIME, self::PPTX_MIME] as $mimeType) {
            $resource = Resource::factory()->file()->create();
            // The declared type has to be in place before ready(), which mirrors
            // it into verified_mime_type and pairs it with verified_size and
            // ready_at. stored_files_verification_consistency requires those
            // three to move together, so both allowlists are exercised by one
            // consistent row rather than by a partial update.
            $file = StoredFile::factory()
                ->forResource($resource)
                ->state([
                    'declared_mime_type' => $mimeType,
                    'original_name' => 'lecture.'.($mimeType === self::DOCX_MIME ? 'docx' : 'pptx'),
                ])
                ->ready()
                ->create();

            self::assertSame(
                $mimeType,
                DB::table('stored_files')->where('id', $file->getKey())->value('verified_mime_type'),
            );
        }

        // The allowlist is still an allowlist: an unlisted OOXML type is rejected.
        $resource = Resource::factory()->file()->create();
        $this->assertQueryRejected('23514', static fn () => StoredFile::factory()->forResource($resource)->create([
            'declared_mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]));
    }

    public function test_migration_rolls_back_when_empty_and_refuses_private_study_data(): void
    {
        $migration = $this->migration();

        $migration->down();
        self::assertFalse(Schema::hasTable('study_artifacts'));
        self::assertFalse(Schema::hasColumn('knowledge_items', 'purpose'));
        self::assertFalse(Schema::hasColumn('knowledge_items', 'intake_item_id'));
        self::assertFalse(Schema::hasColumn('intake_suggestions', 'created_knowledge_item_id'));

        $migration->up();
        self::assertTrue(Schema::hasTable('study_artifacts'));
        self::assertTrue(Schema::hasColumn('knowledge_items', 'purpose'));

        $item = KnowledgeItem::factory()->create();
        $artifactId = $this->insertArtifact($item);

        try {
            $migration->down();
            self::fail('The study migration erased private study data.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('private study data exists', $exception->getMessage());
        }

        self::assertTrue(Schema::hasTable('study_artifacts'));
        $this->assertDatabaseHas('study_artifacts', ['id' => $artifactId]);
    }

    public function test_rollback_also_refuses_a_routed_purpose(): void
    {
        $item = KnowledgeItem::factory()->create();
        DB::table('knowledge_items')->where('id', $item->getKey())->update(['purpose' => 'study']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('private study data exists');

        $this->migration()->down();
    }

    /** @param array<string, mixed> $overrides */
    private function insertArtifact(KnowledgeItem $item, array $overrides = []): int
    {
        return (int) DB::table('study_artifacts')->insertGetId([
            ...[
                'user_id' => $item->user_id,
                'knowledge_item_id' => $item->getKey(),
                'kind' => 'summary',
                'status' => 'queued',
                'payload' => null,
                'schema_version' => 'v1',
                'provider' => null,
                'model' => null,
                'latency_ms' => null,
                'failure_reason' => null,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ...$overrides,
        ]);
    }

    private function migration(): Migration
    {
        $migration = require database_path(self::MIGRATION);
        self::assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM pg_constraint c
             JOIN pg_class t ON t.oid = c.conrelid
             WHERE t.relname = ? AND c.conname = ?',
            [$table, $constraint],
        ) !== null;
    }

    private function indexExists(string $index): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_indexes WHERE indexname = ?', [$index]) !== null;
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
