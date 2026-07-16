<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('source_type', 8);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('context', 2000)->nullable();
            $table->string('state', 24)->default('uploaded_or_linked');
            $table->string('failure_code', 64)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('queued_at', 0)->nullable();
            $table->timestampTz('started_at', 0)->nullable();
            $table->timestampTz('finished_at', 0)->nullable();
            $table->timestampTz('cancelled_at', 0)->nullable();
            $table->timestampsTz(0);
        });

        Schema::create('intake_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('intake_item_id');
            $table->string('kind', 24);
            $table->string('content_type', 255);
            $table->unsignedBigInteger('byte_size')->default(0);
            $table->text('text_content')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz(0);

            $table->unique(['intake_item_id', 'kind'], 'intake_artifacts_item_kind_unique');
        });

        Schema::create('intake_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('intake_item_id');
            $table->string('event', 64);
            $table->string('from_state', 24)->nullable();
            $table->string('to_state', 24)->nullable();
            $table->string('detail', 400)->nullable();
            $table->timestampTz('created_at', 0)->useCurrent();
        });

        DB::statement('ALTER TABLE intake_items ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');

        $this->addConstraints();
        $this->createIndexes();
        $this->createIntegrityTriggers();
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $tables = ['intake_events', 'intake_artifacts', 'intake_items'];

            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::statement("LOCK TABLE {$table} IN ACCESS EXCLUSIVE MODE");
                }
            }

            foreach ($tables as $table) {
                if (Schema::hasTable($table) && DB::table($table)->exists()) {
                    throw new RuntimeException(
                        'Cannot roll back the intake foundation while private intake data exists.',
                    );
                }
            }

            foreach ($tables as $table) {
                Schema::dropIfExists($table);
            }

            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_intake_event_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_intake_artifact_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_enforce_intake_item_transition()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_intake_item_identity_update()');
        }, 3);
    }

    private function addConstraints(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE intake_items
                ADD CONSTRAINT intake_items_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT intake_items_user_foreign
                    FOREIGN KEY (user_id)
                    REFERENCES users (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT intake_items_owner_resource_foreign
                    FOREIGN KEY (user_id, resource_id)
                    REFERENCES resources (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT intake_items_source_type_known CHECK (
                    source_type IN ('file', 'link')
                ),
                ADD CONSTRAINT intake_items_source_consistency CHECK (
                    (
                        source_type = 'file'
                        AND resource_id IS NOT NULL
                        AND url IS NULL
                    )
                    OR (
                        source_type = 'link'
                        AND resource_id IS NULL
                        AND url IS NOT NULL
                        AND url = BTRIM(url)
                        AND url ~ '^https://[^[:space:]]+$'
                    )
                ),
                ADD CONSTRAINT intake_items_context_valid CHECK (
                    context IS NULL
                    OR (
                        context = BTRIM(context)
                        AND CHAR_LENGTH(context) BETWEEN 1 AND 2000
                    )
                ),
                ADD CONSTRAINT intake_items_state_known CHECK (
                    state IN (
                        'uploaded_or_linked',
                        'queued',
                        'extracting',
                        'extracted',
                        'organizing',
                        'awaiting_review',
                        'confirmed',
                        'saved',
                        'failed_retryable',
                        'failed_final',
                        'cancelled'
                    )
                ),
                ADD CONSTRAINT intake_items_failure_code_consistent CHECK (
                    (
                        state IN ('failed_retryable', 'failed_final')
                        AND failure_code IS NOT NULL
                    )
                    OR (
                        state NOT IN ('failed_retryable', 'failed_final')
                        AND failure_code IS NULL
                    )
                ),
                ADD CONSTRAINT intake_items_attempts_bounded CHECK (
                    attempts BETWEEN 0 AND 5
                ),
                ADD CONSTRAINT intake_items_version_positive CHECK (version >= 1),
                ADD CONSTRAINT intake_items_cancel_state_consistent CHECK (
                    (state = 'cancelled') = (cancelled_at IS NOT NULL)
                ),
                ADD CONSTRAINT intake_items_time_order CHECK (
                    updated_at >= created_at
                )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE intake_artifacts
                ADD CONSTRAINT intake_artifacts_item_foreign
                    FOREIGN KEY (intake_item_id)
                    REFERENCES intake_items (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT intake_artifacts_kind_known CHECK (
                    kind IN ('acquired_content', 'extracted_text')
                ),
                ADD CONSTRAINT intake_artifacts_content_type_valid CHECK (
                    content_type = BTRIM(content_type)
                    AND CHAR_LENGTH(content_type) BETWEEN 1 AND 255
                ),
                ADD CONSTRAINT intake_artifacts_byte_size_bounded CHECK (
                    byte_size BETWEEN 0 AND 26214400
                ),
                ADD CONSTRAINT intake_artifacts_text_bounded CHECK (
                    text_content IS NULL
                    OR CHAR_LENGTH(text_content) BETWEEN 1 AND 200000
                ),
                ADD CONSTRAINT intake_artifacts_time_order CHECK (
                    updated_at >= created_at
                )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE intake_events
                ADD CONSTRAINT intake_events_item_foreign
                    FOREIGN KEY (intake_item_id)
                    REFERENCES intake_items (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT intake_events_event_valid CHECK (
                    event = BTRIM(event)
                    AND CHAR_LENGTH(event) BETWEEN 1 AND 64
                ),
                ADD CONSTRAINT intake_events_detail_valid CHECK (
                    detail IS NULL
                    OR (
                        detail = BTRIM(detail)
                        AND CHAR_LENGTH(detail) BETWEEN 1 AND 400
                    )
                )
            SQL);
    }

    private function createIndexes(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX intake_items_owner_created_cursor_idx
            ON intake_items (user_id, created_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX intake_items_owner_state_created_cursor_idx
            ON intake_items (user_id, state, created_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX intake_items_resource_lookup_idx
            ON intake_items (resource_id)
            WHERE resource_id IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX intake_events_item_created_idx
            ON intake_events (intake_item_id, created_at, id)
            SQL);
    }

    private function createIntegrityTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_intake_item_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.public_id IS DISTINCT FROM OLD.public_id
                    OR NEW.user_id IS DISTINCT FROM OLD.user_id
                    OR NEW.source_type IS DISTINCT FROM OLD.source_type
                    OR NEW.resource_id IS DISTINCT FROM OLD.resource_id
                    OR NEW.url IS DISTINCT FROM OLD.url THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'intake_items_identity_immutable',
                        MESSAGE = 'intake_items identity and source are immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER intake_items_identity_immutable
            BEFORE UPDATE OF id, public_id, user_id, source_type, resource_id, url ON intake_items
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_intake_item_identity_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_enforce_intake_item_transition()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.state IS DISTINCT FROM OLD.state
                    AND NOT (
                        (OLD.state = 'uploaded_or_linked' AND NEW.state IN ('queued', 'cancelled'))
                        OR (OLD.state = 'queued' AND NEW.state IN ('extracting', 'cancelled'))
                        OR (OLD.state = 'extracting' AND NEW.state IN ('extracted', 'failed_retryable', 'failed_final', 'cancelled'))
                        OR (OLD.state = 'extracted' AND NEW.state IN ('organizing', 'cancelled'))
                        OR (OLD.state = 'organizing' AND NEW.state IN ('awaiting_review', 'failed_retryable', 'failed_final', 'cancelled'))
                        OR (OLD.state = 'awaiting_review' AND NEW.state IN ('confirmed', 'cancelled'))
                        OR (OLD.state = 'confirmed' AND NEW.state = 'saved')
                        OR (OLD.state = 'failed_retryable' AND NEW.state IN ('queued', 'failed_final', 'cancelled'))
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'intake_items_state_transition_valid',
                        MESSAGE = 'intake_items.state transition is invalid.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER intake_items_transition_valid
            BEFORE UPDATE OF state ON intake_items
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_enforce_intake_item_transition();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_intake_artifact_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION USING
                    ERRCODE = '23514',
                    CONSTRAINT = 'intake_artifacts_immutable',
                    MESSAGE = 'Intake artifacts are immutable once recorded.';
            END;
            $$;

            CREATE TRIGGER intake_artifacts_immutable
            BEFORE UPDATE OF intake_item_id, kind, content_type, byte_size, text_content, metadata ON intake_artifacts
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_intake_artifact_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_intake_event_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION USING
                    ERRCODE = '23514',
                    CONSTRAINT = 'intake_events_append_only',
                    MESSAGE = 'Intake events are append-only.';
            END;
            $$;

            CREATE TRIGGER intake_events_append_only
            BEFORE UPDATE ON intake_events
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_intake_event_update();
            SQL);
    }
};
