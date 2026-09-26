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
        Schema::create('templates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('tool_category_id');
            $table->string('title', 160);
            $table->text('summary')->nullable();
            $table->text('integrity_note')->nullable();
            $table->text('provenance')->nullable();
            $table->string('badge', 32)->default('approved_free');
            $table->string('state', 16)->default('draft');
            $table->timestampTz('last_reviewed_at', 0)->nullable();
            $table->timestampTz('published_at', 0)->nullable();
            $table->timestampTz('archived_at', 0)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);
        });

        Schema::create('template_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedInteger('version_number');
            $table->string('format', 16)->default('markdown');
            $table->text('body');
            $table->text('change_note')->nullable();
            $table->timestampsTz(0);

            $table->unique(['template_id', 'version_number'], 'template_versions_template_version_unique');
        });

        Schema::create('user_template_preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('template_id');
            $table->string('state', 16);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'template_id'], 'user_template_preferences_user_template_unique');
        });

        Schema::create('user_template_copies', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('template_version_id');
            $table->string('destination', 32);
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('title', 160);
            $table->string('format', 16);
            $table->text('body');
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('archived_at', 0)->nullable();
            $table->timestampsTz(0);
        });

        DB::statement('ALTER TABLE templates ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');
        DB::statement('ALTER TABLE user_template_copies ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');

        DB::statement('ALTER TABLE workflow_steps ADD COLUMN template_id BIGINT NULL');

        $this->addConstraints();
        $this->createIndexes();
        $this->createIntegrityTriggers();
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $tables = [
                'user_template_copies',
                'user_template_preferences',
                'template_versions',
                'templates',
            ];

            foreach ([...$tables, 'workflow_steps'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::statement("LOCK TABLE {$table} IN ACCESS EXCLUSIVE MODE");
                }
            }

            $hasTemplateRows = false;

            foreach ($tables as $table) {
                if (Schema::hasTable($table) && DB::table($table)->exists()) {
                    $hasTemplateRows = true;

                    break;
                }
            }

            if (! $hasTemplateRows
                && Schema::hasTable('workflow_steps')
                && Schema::hasColumn('workflow_steps', 'template_id')
                && DB::table('workflow_steps')
                    ->whereNotNull('template_id')
                    ->orWhere('destination_action', 'use_template')
                    ->exists()) {
                $hasTemplateRows = true;
            }

            if ($hasTemplateRows) {
                throw new RuntimeException(
                    'Cannot roll back templates and editable copies while template or user-copy data exists.',
                );
            }

            if (Schema::hasTable('workflow_steps')) {
                DB::statement('ALTER TABLE workflow_steps DROP CONSTRAINT IF EXISTS workflow_steps_destination_known');
                DB::statement(<<<'SQL'
                    ALTER TABLE workflow_steps
                        ADD CONSTRAINT workflow_steps_destination_known CHECK (
                            destination_action IS NULL
                            OR destination_action IN ('create_task', 'save_resource', 'use_tool', 'use_prompt')
                        )
                    SQL);
                DB::statement('ALTER TABLE workflow_steps DROP COLUMN IF EXISTS template_id');
            }

            foreach ($tables as $table) {
                Schema::dropIfExists($table);
            }

            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_template_copy_identity_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_template_preference_identity_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_enforce_template_version_draft_only()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_template_version_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_enforce_template_transition()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_template_identity_update()');
        }, 3);
    }

    private function addConstraints(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE templates
                ADD CONSTRAINT templates_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT templates_category_foreign
                    FOREIGN KEY (tool_category_id)
                    REFERENCES tool_categories (id)
                    ON UPDATE RESTRICT
                    ON DELETE RESTRICT
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT templates_title_valid CHECK (
                    title = BTRIM(title)
                    AND CHAR_LENGTH(title) BETWEEN 1 AND 160
                ),
                ADD CONSTRAINT templates_publication_text_valid CHECK (
                    (
                        summary IS NULL
                        OR (
                            summary = BTRIM(summary)
                            AND CHAR_LENGTH(summary) BETWEEN 1 AND 2000
                        )
                    )
                    AND (
                        integrity_note IS NULL
                        OR (
                            integrity_note = BTRIM(integrity_note)
                            AND CHAR_LENGTH(integrity_note) BETWEEN 1 AND 2000
                        )
                    )
                    AND (
                        provenance IS NULL
                        OR (
                            provenance = BTRIM(provenance)
                            AND CHAR_LENGTH(provenance) BETWEEN 1 AND 2000
                        )
                    )
                ),
                ADD CONSTRAINT templates_badge_known CHECK (
                    badge = 'approved_free'
                ),
                ADD CONSTRAINT templates_state_known CHECK (
                    state IN ('draft', 'in_review', 'published', 'archived')
                ),
                ADD CONSTRAINT templates_published_content_complete CHECK (
                    state NOT IN ('published', 'archived')
                    OR (
                        summary IS NOT NULL
                        AND integrity_note IS NOT NULL
                        AND provenance IS NOT NULL
                    )
                ),
                ADD CONSTRAINT templates_lifecycle_consistent CHECK (
                    (
                        state IN ('draft', 'in_review')
                        AND last_reviewed_at IS NULL
                        AND published_at IS NULL
                        AND archived_at IS NULL
                    )
                    OR (
                        state = 'published'
                        AND last_reviewed_at IS NOT NULL
                        AND published_at IS NOT NULL
                        AND archived_at IS NULL
                    )
                    OR (
                        state = 'archived'
                        AND last_reviewed_at IS NOT NULL
                        AND published_at IS NOT NULL
                        AND archived_at IS NOT NULL
                    )
                ),
                ADD CONSTRAINT templates_lifecycle_time_order CHECK (
                    (last_reviewed_at IS NULL OR published_at IS NULL OR last_reviewed_at <= published_at)
                    AND (published_at IS NULL OR archived_at IS NULL OR published_at <= archived_at)
                    AND updated_at >= created_at
                ),
                ADD CONSTRAINT templates_version_positive CHECK (version >= 1)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE template_versions
                ADD CONSTRAINT template_versions_template_foreign
                    FOREIGN KEY (template_id)
                    REFERENCES templates (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT template_versions_number_bounded CHECK (
                    version_number BETWEEN 1 AND 100
                ),
                ADD CONSTRAINT template_versions_format_known CHECK (
                    format IN ('markdown', 'plain')
                ),
                ADD CONSTRAINT template_versions_body_valid CHECK (
                    body = BTRIM(body)
                    AND CHAR_LENGTH(body) BETWEEN 1 AND 20000
                ),
                ADD CONSTRAINT template_versions_change_note_valid CHECK (
                    change_note IS NULL
                    OR (
                        change_note = BTRIM(change_note)
                        AND CHAR_LENGTH(change_note) BETWEEN 1 AND 2000
                    )
                ),
                ADD CONSTRAINT template_versions_timestamp_order CHECK (
                    updated_at >= created_at
                )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE user_template_preferences
                ADD CONSTRAINT user_template_preferences_state_known CHECK (
                    state IN ('saved', 'dismissed')
                ),
                ADD CONSTRAINT user_template_preferences_timestamp_order CHECK (
                    updated_at >= created_at
                ),
                ADD CONSTRAINT user_template_preferences_user_foreign
                    FOREIGN KEY (user_id)
                    REFERENCES users (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT user_template_preferences_template_foreign
                    FOREIGN KEY (template_id)
                    REFERENCES templates (id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE user_template_copies
                ADD CONSTRAINT user_template_copies_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT user_template_copies_user_foreign
                    FOREIGN KEY (user_id)
                    REFERENCES users (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT user_template_copies_template_foreign
                    FOREIGN KEY (template_id)
                    REFERENCES templates (id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT user_template_copies_version_foreign
                    FOREIGN KEY (template_version_id)
                    REFERENCES template_versions (id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT user_template_copies_owner_course_foreign
                    FOREIGN KEY (user_id, course_id)
                    REFERENCES courses (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT user_template_copies_destination_known CHECK (
                    destination IN ('dashboard', 'course')
                ),
                ADD CONSTRAINT user_template_copies_course_target_consistent CHECK (
                    (destination = 'course' AND course_id IS NOT NULL)
                    OR (destination <> 'course' AND course_id IS NULL)
                ),
                ADD CONSTRAINT user_template_copies_title_valid CHECK (
                    title = BTRIM(title)
                    AND CHAR_LENGTH(title) BETWEEN 1 AND 160
                ),
                ADD CONSTRAINT user_template_copies_format_known CHECK (
                    format IN ('markdown', 'plain')
                ),
                ADD CONSTRAINT user_template_copies_body_valid CHECK (
                    body = BTRIM(body)
                    AND CHAR_LENGTH(body) BETWEEN 1 AND 20000
                ),
                ADD CONSTRAINT user_template_copies_version_positive CHECK (version >= 1),
                ADD CONSTRAINT user_template_copies_time_order CHECK (
                    updated_at >= created_at
                    AND (archived_at IS NULL OR archived_at >= created_at)
                )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE workflow_steps
                ADD CONSTRAINT workflow_steps_template_foreign
                    FOREIGN KEY (template_id)
                    REFERENCES templates (id)
                    ON UPDATE RESTRICT
                    ON DELETE RESTRICT
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        DB::statement('ALTER TABLE workflow_steps DROP CONSTRAINT workflow_steps_destination_known');
        DB::statement(<<<'SQL'
            ALTER TABLE workflow_steps
                ADD CONSTRAINT workflow_steps_destination_known CHECK (
                    destination_action IS NULL
                    OR destination_action IN ('create_task', 'save_resource', 'use_tool', 'use_prompt', 'use_template')
                )
            SQL);
    }

    private function createIndexes(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX templates_category_lookup_idx
            ON templates (tool_category_id, id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX templates_admin_state_updated_cursor_idx
            ON templates (state, updated_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX templates_published_title_cursor_idx
            ON templates (LOWER(title), public_id)
            WHERE state = 'published' AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX templates_published_category_title_cursor_idx
            ON templates (tool_category_id, LOWER(title), public_id)
            WHERE state = 'published' AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX templates_published_reviewed_cursor_idx
            ON templates (last_reviewed_at DESC, public_id DESC)
            WHERE state = 'published' AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX template_versions_template_latest_idx
            ON template_versions (template_id, version_number DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_template_preferences_user_state_updated_idx
            ON user_template_preferences (user_id, state, updated_at DESC, template_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_template_preferences_template_user_idx
            ON user_template_preferences (template_id, user_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX user_template_copies_active_dashboard_unique
            ON user_template_copies (user_id, template_id, destination)
            WHERE archived_at IS NULL AND course_id IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX user_template_copies_active_course_unique
            ON user_template_copies (user_id, template_id, destination, course_id)
            WHERE archived_at IS NULL AND course_id IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_template_copies_owner_active_created_cursor_idx
            ON user_template_copies (user_id, created_at DESC, public_id DESC)
            WHERE archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_template_copies_owner_created_cursor_idx
            ON user_template_copies (user_id, created_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_template_copies_owner_active_title_cursor_idx
            ON user_template_copies (user_id, LOWER(title), public_id)
            WHERE archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_template_copies_template_user_idx
            ON user_template_copies (template_id, user_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_template_copies_version_lookup_idx
            ON user_template_copies (template_version_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_template_copies_course_lookup_idx
            ON user_template_copies (course_id)
            WHERE course_id IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX workflow_steps_template_lookup_idx
            ON workflow_steps (template_id)
            WHERE template_id IS NOT NULL
            SQL);
    }

    private function createIntegrityTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_template_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'templates_id_immutable',
                        MESSAGE = 'templates.id is immutable.';
                END IF;

                IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'templates_public_id_immutable',
                        MESSAGE = 'templates.public_id is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER templates_identity_immutable
            BEFORE UPDATE OF id, public_id ON templates
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_template_identity_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_enforce_template_transition()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            DECLARE
                content_changed BOOLEAN;
            BEGIN
                IF NEW.state IS DISTINCT FROM OLD.state
                    AND NOT (
                        (OLD.state = 'draft' AND NEW.state = 'in_review')
                        OR (OLD.state = 'in_review' AND NEW.state IN ('draft', 'published'))
                        OR (OLD.state = 'published' AND NEW.state IN ('draft', 'archived'))
                        OR (OLD.state = 'archived' AND NEW.state = 'draft')
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'templates_state_transition_valid',
                        MESSAGE = 'templates.state transition is invalid.';
                END IF;

                IF NEW.state = 'published' AND OLD.state <> 'published'
                    AND NOT EXISTS (
                        SELECT 1
                        FROM template_versions
                        WHERE template_versions.template_id = NEW.id
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'templates_publication_requires_version',
                        MESSAGE = 'A template needs at least one immutable version before publication.';
                END IF;

                content_changed := NEW.tool_category_id IS DISTINCT FROM OLD.tool_category_id
                    OR NEW.title IS DISTINCT FROM OLD.title
                    OR NEW.summary IS DISTINCT FROM OLD.summary
                    OR NEW.integrity_note IS DISTINCT FROM OLD.integrity_note
                    OR NEW.provenance IS DISTINCT FROM OLD.provenance
                    OR NEW.badge IS DISTINCT FROM OLD.badge;

                IF content_changed AND OLD.state <> 'draft'
                    AND NOT (
                        NEW.state = 'draft'
                        AND NEW.last_reviewed_at IS NULL
                        AND NEW.published_at IS NULL
                        AND NEW.archived_at IS NULL
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'templates_content_edit_invalidates_review',
                        MESSAGE = 'Reviewed template content must return to draft before it changes.';
                END IF;

                IF OLD.state = 'published' AND NEW.state = 'archived'
                    AND (
                        NEW.last_reviewed_at IS DISTINCT FROM OLD.last_reviewed_at
                        OR NEW.published_at IS DISTINCT FROM OLD.published_at
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'templates_archive_preserves_review',
                        MESSAGE = 'Archiving must preserve reviewed publication metadata.';
                END IF;

                IF NEW.state = OLD.state AND OLD.state IN ('published', 'archived')
                    AND (
                        NEW.last_reviewed_at IS DISTINCT FROM OLD.last_reviewed_at
                        OR NEW.published_at IS DISTINCT FROM OLD.published_at
                        OR NEW.archived_at IS DISTINCT FROM OLD.archived_at
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'templates_review_metadata_immutable_in_state',
                        MESSAGE = 'Reviewed publication metadata is immutable without a lifecycle transition.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER templates_transition_valid
            BEFORE UPDATE OF
                tool_category_id,
                title,
                summary,
                integrity_note,
                provenance,
                badge,
                state,
                last_reviewed_at,
                published_at,
                archived_at
            ON templates
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_enforce_template_transition();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_template_version_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.template_id IS DISTINCT FROM OLD.template_id
                    OR NEW.version_number IS DISTINCT FROM OLD.version_number
                    OR NEW.format IS DISTINCT FROM OLD.format
                    OR NEW.body IS DISTINCT FROM OLD.body
                    OR NEW.change_note IS DISTINCT FROM OLD.change_note THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'template_versions_immutable',
                        MESSAGE = 'Template versions are immutable once created.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER template_versions_immutable
            BEFORE UPDATE OF id, template_id, version_number, format, body, change_note ON template_versions
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_template_version_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_enforce_template_version_draft_only()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            DECLARE
                template_state TEXT;
                target_id BIGINT;
            BEGIN
                target_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.template_id ELSE NEW.template_id END;

                SELECT state INTO template_state
                FROM templates
                WHERE id = target_id
                FOR UPDATE;

                -- A missing parent row means the template itself is being
                -- deleted and its versions are cascading with it.
                IF template_state IS NULL THEN
                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;

                    RETURN NEW;
                END IF;

                IF template_state IS DISTINCT FROM 'draft' THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'template_versions_only_changed_in_draft',
                        MESSAGE = 'Template versions can only be added or removed while the template is a draft.';
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER template_versions_draft_only
            BEFORE INSERT OR DELETE ON template_versions
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_enforce_template_version_draft_only();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_template_preference_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.user_id IS DISTINCT FROM OLD.user_id
                    OR NEW.template_id IS DISTINCT FROM OLD.template_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'user_template_preferences_identity_immutable',
                        MESSAGE = 'user_template_preferences identity is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER user_template_preferences_identity_immutable
            BEFORE UPDATE OF id, user_id, template_id ON user_template_preferences
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_template_preference_identity_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_template_copy_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.public_id IS DISTINCT FROM OLD.public_id
                    OR NEW.user_id IS DISTINCT FROM OLD.user_id
                    OR NEW.template_id IS DISTINCT FROM OLD.template_id
                    OR NEW.template_version_id IS DISTINCT FROM OLD.template_version_id
                    OR NEW.destination IS DISTINCT FROM OLD.destination
                    OR NEW.course_id IS DISTINCT FROM OLD.course_id
                    OR NEW.format IS DISTINCT FROM OLD.format THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'user_template_copies_identity_immutable',
                        MESSAGE = 'user_template_copies identity and provenance are immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER user_template_copies_identity_immutable
            BEFORE UPDATE OF id, public_id, user_id, template_id, template_version_id, destination, course_id, format ON user_template_copies
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_template_copy_identity_update();
            SQL);
    }
};
