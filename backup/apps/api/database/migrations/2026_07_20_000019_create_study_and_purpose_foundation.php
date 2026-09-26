<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Second Brain companion + Study workspace foundation (ADR-0034, ADR-0035).
 *
 * This is the single consolidated migration for the information-architecture
 * overhaul. Eleven of the twelve plan items ship at zero schema cost; only the
 * Second Brain companion and the Study section need persistence, and they share
 * the same needs, so they are approved and applied together.
 *
 * Seven parts:
 *  1. knowledge_items.purpose            - the organizing principle of the new IA.
 *  2. knowledge_items.intake_item_id     - the missing join to extracted text.
 *                                          Requires UNIQUE(user_id, id) on
 *                                          intake_items, which does not exist
 *                                          yet and is added here.
 *  3. intake_suggestions.kind            - accepts 'knowledge_item'.
 *  4. intake_suggestions.created_knowledge_item_id - confirmation provenance.
 *  5. study_artifacts                    - generated study material. The row IS
 *                                          the dedupe/cache, hence
 *                                          UNIQUE(knowledge_item_id, kind).
 *  6. stored_files MIME CHECKs           - accept DOCX and PPTX.
 *  7. down() refuses to erase private data, mirroring ..._000014.
 *
 * Deliberately NOT here (each needs its own approval): dropping research_topics,
 * a tools.search_vector, users.timezone/theme, notification tables, or a
 * mentor_requests(requester_id, status) index.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ORIGINAL_STORED_FILE_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
        'text/markdown',
    ];

    /** @var list<string> */
    private const OOXML_STORED_FILE_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    public function up(): void
    {
        $this->addIntakeItemOwnerUnique();
        $this->extendKnowledgeItems();
        $this->extendIntakeSuggestions();
        $this->createStudyArtifacts();
        $this->setStoredFileMimeTypes([...self::ORIGINAL_STORED_FILE_MIME_TYPES, ...self::OOXML_STORED_FILE_MIME_TYPES]);
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->guardPrivateStudyData();

            Schema::dropIfExists('study_artifacts');

            if (Schema::hasTable('intake_suggestions')) {
                DB::statement('ALTER TABLE intake_suggestions DROP CONSTRAINT IF EXISTS intake_suggestions_created_targets_consistent');
                DB::statement('ALTER TABLE intake_suggestions DROP CONSTRAINT IF EXISTS intake_suggestions_knowledge_item_foreign');
                DB::statement('DROP INDEX IF EXISTS intake_suggestions_knowledge_item_lookup_idx');
                DB::statement('ALTER TABLE intake_suggestions DROP COLUMN IF EXISTS created_knowledge_item_id');

                DB::statement('ALTER TABLE intake_suggestions DROP CONSTRAINT IF EXISTS intake_suggestions_kind_known');
                DB::statement(<<<'SQL'
                    ALTER TABLE intake_suggestions
                        ADD CONSTRAINT intake_suggestions_kind_known CHECK (
                            kind IN ('task', 'resource')
                        ),
                        ADD CONSTRAINT intake_suggestions_created_targets_consistent CHECK (
                            status = 'applied'
                            OR (created_task_id IS NULL AND created_resource_id IS NULL)
                        )
                    SQL);
            }

            if (Schema::hasTable('knowledge_items')) {
                DB::statement('ALTER TABLE knowledge_items DROP CONSTRAINT IF EXISTS knowledge_items_owner_intake_item_foreign');
                DB::statement('ALTER TABLE knowledge_items DROP CONSTRAINT IF EXISTS knowledge_items_purpose_known');
                DB::statement('DROP INDEX IF EXISTS knowledge_items_owner_intake_item_idx');
                DB::statement('ALTER TABLE knowledge_items DROP COLUMN IF EXISTS intake_item_id');
                DB::statement('ALTER TABLE knowledge_items DROP COLUMN IF EXISTS purpose');
            }

            // `intake_items_owner_id_unique` is deliberately NOT dropped. It is the
            // tenancy invariant every other owner-scoped table already carries, it
            // is correct independently of this migration, and up() re-adds it only
            // when missing, so leaving it is idempotent on re-migrate.

            $this->setStoredFileMimeTypes(self::ORIGINAL_STORED_FILE_MIME_TYPES);
        }, 3);
    }

    /**
     * Part 7. Private study material, routed purposes, and knowledge provenance
     * are all student-owned data. Refuse the rollback rather than erase them.
     */
    private function guardPrivateStudyData(): void
    {
        if (Schema::hasTable('study_artifacts')) {
            DB::statement('LOCK TABLE study_artifacts IN ACCESS EXCLUSIVE MODE');

            if (DB::table('study_artifacts')->exists()) {
                throw new RuntimeException(
                    'Cannot roll back the study and purpose foundation while private study data exists.',
                );
            }
        }

        if (Schema::hasTable('knowledge_items')
            && Schema::hasColumn('knowledge_items', 'purpose')
            && DB::table('knowledge_items')
                ->whereNotNull('purpose')
                ->orWhereNotNull('intake_item_id')
                ->exists()) {
            throw new RuntimeException(
                'Cannot roll back the study and purpose foundation while private study data exists.',
            );
        }

        if (Schema::hasTable('intake_suggestions')
            && Schema::hasColumn('intake_suggestions', 'created_knowledge_item_id')
            && DB::table('intake_suggestions')->whereNotNull('created_knowledge_item_id')->exists()) {
            throw new RuntimeException(
                'Cannot roll back the study and purpose foundation while private study data exists.',
            );
        }
    }

    /**
     * Part 2 (prerequisite). The composite tenant-scoped FK
     * (user_id, intake_item_id) -> intake_items(user_id, id) requires a unique
     * constraint on the referenced pair. `..._000013` created `intake_items`
     * without one (unlike every other owner-scoped table), so it is added here.
     */
    private function addIntakeItemOwnerUnique(): void
    {
        if ($this->constraintExists('intake_items', 'intake_items_owner_id_unique')) {
            return;
        }

        DB::statement('ALTER TABLE intake_items ADD CONSTRAINT intake_items_owner_id_unique UNIQUE (user_id, id)');
    }

    /** Parts 1 and 2. */
    private function extendKnowledgeItems(): void
    {
        DB::statement('ALTER TABLE knowledge_items ADD COLUMN purpose VARCHAR(16) NULL');
        DB::statement('ALTER TABLE knowledge_items ADD COLUMN intake_item_id BIGINT NULL');

        DB::statement(<<<'SQL'
            ALTER TABLE knowledge_items
                ADD CONSTRAINT knowledge_items_purpose_known CHECK (
                    purpose IS NULL
                    OR purpose IN ('resource', 'study', 'research', 'exam')
                ),
                ADD CONSTRAINT knowledge_items_owner_intake_item_foreign
                    FOREIGN KEY (user_id, intake_item_id)
                    REFERENCES intake_items (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX knowledge_items_owner_intake_item_idx
            ON knowledge_items (user_id, intake_item_id)
            WHERE intake_item_id IS NOT NULL
            SQL);
    }

    /** Parts 3 and 4. */
    private function extendIntakeSuggestions(): void
    {
        DB::statement('ALTER TABLE intake_suggestions ADD COLUMN created_knowledge_item_id BIGINT NULL');

        DB::statement('ALTER TABLE intake_suggestions DROP CONSTRAINT intake_suggestions_kind_known');
        DB::statement('ALTER TABLE intake_suggestions DROP CONSTRAINT intake_suggestions_created_targets_consistent');

        // The created_* foreign keys are single-column by design: intake_suggestions
        // carries no user_id of its own; tenancy is inherited through intake_item_id.
        // This mirrors intake_suggestions_task_foreign / _resource_foreign exactly.
        DB::statement(<<<'SQL'
            ALTER TABLE intake_suggestions
                ADD CONSTRAINT intake_suggestions_kind_known CHECK (
                    kind IN ('task', 'resource', 'knowledge_item')
                ),
                ADD CONSTRAINT intake_suggestions_knowledge_item_foreign
                    FOREIGN KEY (created_knowledge_item_id)
                    REFERENCES knowledge_items (id)
                    ON UPDATE RESTRICT
                    ON DELETE SET NULL
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT intake_suggestions_created_targets_consistent CHECK (
                    status = 'applied'
                    OR (
                        created_task_id IS NULL
                        AND created_resource_id IS NULL
                        AND created_knowledge_item_id IS NULL
                    )
                )
            SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX intake_suggestions_knowledge_item_lookup_idx
            ON intake_suggestions (created_knowledge_item_id)
            WHERE created_knowledge_item_id IS NOT NULL
            SQL);
    }

    /** Part 5. */
    private function createStudyArtifacts(): void
    {
        Schema::create('study_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('knowledge_item_id');
            $table->string('kind', 24);
            $table->string('status', 16)->default('queued');
            $table->jsonb('payload')->nullable();
            $table->string('schema_version', 16)->default('v1');
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->integer('latency_ms')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'id'], 'study_artifacts_owner_id_unique');
            $table->unique(['knowledge_item_id', 'kind'], 'study_artifacts_item_kind_unique');
        });

        DB::statement('ALTER TABLE study_artifacts ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');

        DB::statement(<<<'SQL'
            ALTER TABLE study_artifacts
                ADD CONSTRAINT study_artifacts_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT study_artifacts_owner_item_foreign
                    FOREIGN KEY (user_id, knowledge_item_id)
                    REFERENCES knowledge_items (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT study_artifacts_kind_known CHECK (
                    kind IN ('summary', 'topic_explanation', 'quick_learn', 'exam_questions')
                ),
                ADD CONSTRAINT study_artifacts_status_known CHECK (
                    status IN ('queued', 'running', 'ready', 'failed')
                ),
                ADD CONSTRAINT study_artifacts_payload_is_object CHECK (
                    payload IS NULL OR JSONB_TYPEOF(payload) = 'object'
                ),
                ADD CONSTRAINT study_artifacts_payload_status_consistent CHECK (
                    (status = 'ready' AND payload IS NOT NULL)
                    OR (status <> 'ready' AND payload IS NULL)
                ),
                ADD CONSTRAINT study_artifacts_failure_reason_consistent CHECK (
                    (
                        failure_reason IS NULL
                        OR (
                            status = 'failed'
                            AND failure_reason = BTRIM(failure_reason)
                            AND CHAR_LENGTH(failure_reason) BETWEEN 1 AND 255
                        )
                    )
                ),
                ADD CONSTRAINT study_artifacts_schema_version_valid CHECK (
                    schema_version = BTRIM(schema_version)
                    AND CHAR_LENGTH(schema_version) BETWEEN 1 AND 16
                ),
                ADD CONSTRAINT study_artifacts_attribution_valid CHECK (
                    (
                        provider IS NULL
                        OR (
                            provider = BTRIM(provider)
                            AND CHAR_LENGTH(provider) BETWEEN 1 AND 64
                        )
                    )
                    AND (
                        model IS NULL
                        OR (
                            model = BTRIM(model)
                            AND CHAR_LENGTH(model) BETWEEN 1 AND 128
                        )
                    )
                ),
                ADD CONSTRAINT study_artifacts_latency_bounded CHECK (
                    latency_ms IS NULL OR latency_ms >= 0
                ),
                ADD CONSTRAINT study_artifacts_version_positive CHECK (version >= 1),
                ADD CONSTRAINT study_artifacts_time_order CHECK (
                    updated_at >= created_at
                )
            SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX study_artifacts_owner_created_cursor_idx
            ON study_artifacts (user_id, created_at DESC, public_id DESC)
            SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX study_artifacts_owner_status_idx
            ON study_artifacts (user_id, status, id)
            SQL);

        // Reuses the Second Brain identity guard installed by ..._000015.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER study_artifacts_identity_immutable
            BEFORE UPDATE OF public_id, user_id ON study_artifacts
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_brain_identity_update();
            SQL);
    }

    /**
     * Part 6. Both stored_files MIME allowlists are CHECK constraints, so the
     * DOCX/PPTX upload path stays closed until they are rewritten.
     *
     * @param  list<string>  $mimeTypes
     */
    private function setStoredFileMimeTypes(array $mimeTypes): void
    {
        if (! Schema::hasTable('stored_files')) {
            return;
        }

        $list = implode(', ', array_map(static fn (string $mimeType): string => "'{$mimeType}'", $mimeTypes));

        DB::statement('ALTER TABLE stored_files DROP CONSTRAINT IF EXISTS stored_files_declared_mime_type_allowed');
        DB::statement('ALTER TABLE stored_files DROP CONSTRAINT IF EXISTS stored_files_verified_mime_type_allowed');

        DB::statement(<<<SQL
            ALTER TABLE stored_files
                ADD CONSTRAINT stored_files_declared_mime_type_allowed CHECK (
                    declared_mime_type IN ({$list})
                ),
                ADD CONSTRAINT stored_files_verified_mime_type_allowed CHECK (
                    verified_mime_type IS NULL
                    OR verified_mime_type IN ({$list})
                )
            SQL);
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM pg_constraint c
             JOIN pg_class t ON t.oid = c.conrelid
             JOIN pg_namespace n ON n.oid = t.relnamespace
             WHERE n.nspname = CURRENT_SCHEMA() AND t.relname = ? AND c.conname = ?',
            [$table, $constraint],
        ) !== null;
    }
};
