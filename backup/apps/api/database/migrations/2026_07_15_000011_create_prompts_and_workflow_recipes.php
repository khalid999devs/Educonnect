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
        Schema::create('prompt_templates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('tool_category_id');
            $table->string('title', 160);
            $table->text('purpose')->nullable();
            $table->text('template_body')->nullable();
            $table->jsonb('placeholders')->nullable();
            $table->text('expected_output')->nullable();
            $table->text('integrity_note')->nullable();
            $table->text('provenance')->nullable();
            $table->string('state', 16)->default('draft');
            $table->timestampTz('last_reviewed_at', 0)->nullable();
            $table->timestampTz('published_at', 0)->nullable();
            $table->timestampTz('archived_at', 0)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);
        });

        Schema::create('prompt_template_tool', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('prompt_template_id');
            $table->unsignedBigInteger('tool_id');
            $table->timestampsTz(0);

            $table->unique(['prompt_template_id', 'tool_id'], 'prompt_template_tool_pair_unique');
        });

        Schema::create('workflow_recipes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('tool_category_id');
            $table->string('title', 160);
            $table->text('goal')->nullable();
            $table->text('expected_outcome')->nullable();
            $table->text('integrity_note')->nullable();
            $table->text('provenance')->nullable();
            $table->string('state', 16)->default('draft');
            $table->timestampTz('last_reviewed_at', 0)->nullable();
            $table->timestampTz('published_at', 0)->nullable();
            $table->timestampTz('archived_at', 0)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);
        });

        Schema::create('workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workflow_recipe_id');
            $table->unsignedInteger('step_number');
            $table->string('title', 160);
            $table->text('instruction');
            $table->unsignedBigInteger('tool_id')->nullable();
            $table->unsignedBigInteger('prompt_template_id')->nullable();
            $table->string('destination_action', 32)->nullable();
            $table->timestampsTz(0);

            $table->unique(['workflow_recipe_id', 'step_number'], 'workflow_steps_recipe_step_unique');
        });

        Schema::create('user_prompt_preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('prompt_template_id');
            $table->string('state', 16);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'prompt_template_id'], 'user_prompt_preferences_user_prompt_unique');
        });

        Schema::create('user_prompt_copies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('prompt_template_id');
            $table->unsignedInteger('copy_count')->default(1);
            $table->timestampTz('first_copied_at', 0);
            $table->timestampTz('last_copied_at', 0);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'prompt_template_id'], 'user_prompt_copies_user_prompt_unique');
        });

        Schema::create('user_workflow_preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workflow_recipe_id');
            $table->string('state', 16);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'workflow_recipe_id'], 'user_workflow_preferences_user_workflow_unique');
        });

        DB::statement('ALTER TABLE prompt_templates ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');
        DB::statement('ALTER TABLE workflow_recipes ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');

        $this->createPlaceholderValidationFunction();
        $this->addConstraints();
        $this->createIndexes();
        $this->createIntegrityTriggers();
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $tables = [
                'user_workflow_preferences',
                'user_prompt_copies',
                'user_prompt_preferences',
                'workflow_steps',
                'workflow_recipes',
                'prompt_template_tool',
                'prompt_templates',
            ];

            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::statement("LOCK TABLE {$table} IN ACCESS EXCLUSIVE MODE");
                }
            }

            $hasGuidanceRows = false;

            foreach ($tables as $table) {
                if (Schema::hasTable($table) && DB::table($table)->exists()) {
                    $hasGuidanceRows = true;

                    break;
                }
            }

            if ($hasGuidanceRows) {
                throw new RuntimeException(
                    'Cannot roll back prompts and workflow recipes while guidance or user-preference data exists.',
                );
            }

            foreach ($tables as $table) {
                Schema::dropIfExists($table);
            }

            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_workflow_preference_identity_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_prompt_copy_identity_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_prompt_preference_identity_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_enforce_workflow_recipe_transition()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_workflow_recipe_identity_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_enforce_prompt_template_transition()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_prompt_template_identity_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_prompt_placeholders_valid(JSONB)');
        }, 3);
    }

    private function createPlaceholderValidationFunction(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_prompt_placeholders_valid(candidate JSONB)
            RETURNS BOOLEAN
            LANGUAGE plpgsql
            IMMUTABLE
            STRICT
            PARALLEL SAFE
            AS $$
            DECLARE
                item JSONB;
                item_text TEXT;
            BEGIN
                IF JSONB_TYPEOF(candidate) <> 'array' THEN
                    RETURN FALSE;
                END IF;

                IF JSONB_ARRAY_LENGTH(candidate) NOT BETWEEN 1 AND 20 THEN
                    RETURN FALSE;
                END IF;

                FOR item IN
                    SELECT entry.value
                    FROM JSONB_ARRAY_ELEMENTS(candidate) AS entry(value)
                LOOP
                    IF JSONB_TYPEOF(item) <> 'string' THEN
                        RETURN FALSE;
                    END IF;

                    item_text := item #>> '{}';

                    IF item_text <> BTRIM(item_text)
                        OR CHAR_LENGTH(item_text) NOT BETWEEN 1 AND 100 THEN
                        RETURN FALSE;
                    END IF;
                END LOOP;

                RETURN TRUE;
            END;
            $$;
            SQL);
    }

    private function addConstraints(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE prompt_templates
                ADD CONSTRAINT prompt_templates_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT prompt_templates_category_foreign
                    FOREIGN KEY (tool_category_id)
                    REFERENCES tool_categories (id)
                    ON UPDATE RESTRICT
                    ON DELETE RESTRICT
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT prompt_templates_title_valid CHECK (
                    title = BTRIM(title)
                    AND CHAR_LENGTH(title) BETWEEN 1 AND 160
                ),
                ADD CONSTRAINT prompt_templates_publication_text_valid CHECK (
                    (
                        purpose IS NULL
                        OR (
                            purpose = BTRIM(purpose)
                            AND CHAR_LENGTH(purpose) BETWEEN 1 AND 2000
                        )
                    )
                    AND (
                        template_body IS NULL
                        OR (
                            template_body = BTRIM(template_body)
                            AND CHAR_LENGTH(template_body) BETWEEN 1 AND 8000
                        )
                    )
                    AND (
                        expected_output IS NULL
                        OR (
                            expected_output = BTRIM(expected_output)
                            AND CHAR_LENGTH(expected_output) BETWEEN 1 AND 4000
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
                ADD CONSTRAINT prompt_templates_placeholders_valid CHECK (
                    placeholders IS NULL OR educonnect_prompt_placeholders_valid(placeholders)
                ),
                ADD CONSTRAINT prompt_templates_state_known CHECK (
                    state IN ('draft', 'in_review', 'published', 'archived')
                ),
                ADD CONSTRAINT prompt_templates_published_content_complete CHECK (
                    state NOT IN ('published', 'archived')
                    OR (
                        purpose IS NOT NULL
                        AND template_body IS NOT NULL
                        AND placeholders IS NOT NULL
                        AND expected_output IS NOT NULL
                        AND integrity_note IS NOT NULL
                        AND provenance IS NOT NULL
                    )
                ),
                ADD CONSTRAINT prompt_templates_lifecycle_consistent CHECK (
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
                ADD CONSTRAINT prompt_templates_lifecycle_time_order CHECK (
                    (last_reviewed_at IS NULL OR published_at IS NULL OR last_reviewed_at <= published_at)
                    AND (published_at IS NULL OR archived_at IS NULL OR published_at <= archived_at)
                    AND updated_at >= created_at
                ),
                ADD CONSTRAINT prompt_templates_version_positive CHECK (version >= 1)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE prompt_template_tool
                ADD CONSTRAINT prompt_template_tool_prompt_foreign
                    FOREIGN KEY (prompt_template_id)
                    REFERENCES prompt_templates (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT prompt_template_tool_tool_foreign
                    FOREIGN KEY (tool_id)
                    REFERENCES tools (id)
                    ON UPDATE RESTRICT
                    ON DELETE RESTRICT
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT prompt_template_tool_timestamp_order CHECK (
                    updated_at >= created_at
                )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE workflow_recipes
                ADD CONSTRAINT workflow_recipes_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT workflow_recipes_category_foreign
                    FOREIGN KEY (tool_category_id)
                    REFERENCES tool_categories (id)
                    ON UPDATE RESTRICT
                    ON DELETE RESTRICT
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT workflow_recipes_title_valid CHECK (
                    title = BTRIM(title)
                    AND CHAR_LENGTH(title) BETWEEN 1 AND 160
                ),
                ADD CONSTRAINT workflow_recipes_publication_text_valid CHECK (
                    (
                        goal IS NULL
                        OR (
                            goal = BTRIM(goal)
                            AND CHAR_LENGTH(goal) BETWEEN 1 AND 2000
                        )
                    )
                    AND (
                        expected_outcome IS NULL
                        OR (
                            expected_outcome = BTRIM(expected_outcome)
                            AND CHAR_LENGTH(expected_outcome) BETWEEN 1 AND 4000
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
                ADD CONSTRAINT workflow_recipes_state_known CHECK (
                    state IN ('draft', 'in_review', 'published', 'archived')
                ),
                ADD CONSTRAINT workflow_recipes_published_content_complete CHECK (
                    state NOT IN ('published', 'archived')
                    OR (
                        goal IS NOT NULL
                        AND expected_outcome IS NOT NULL
                        AND integrity_note IS NOT NULL
                        AND provenance IS NOT NULL
                    )
                ),
                ADD CONSTRAINT workflow_recipes_lifecycle_consistent CHECK (
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
                ADD CONSTRAINT workflow_recipes_lifecycle_time_order CHECK (
                    (last_reviewed_at IS NULL OR published_at IS NULL OR last_reviewed_at <= published_at)
                    AND (published_at IS NULL OR archived_at IS NULL OR published_at <= archived_at)
                    AND updated_at >= created_at
                ),
                ADD CONSTRAINT workflow_recipes_version_positive CHECK (version >= 1)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE workflow_steps
                ADD CONSTRAINT workflow_steps_recipe_foreign
                    FOREIGN KEY (workflow_recipe_id)
                    REFERENCES workflow_recipes (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT workflow_steps_tool_foreign
                    FOREIGN KEY (tool_id)
                    REFERENCES tools (id)
                    ON UPDATE RESTRICT
                    ON DELETE RESTRICT
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT workflow_steps_prompt_foreign
                    FOREIGN KEY (prompt_template_id)
                    REFERENCES prompt_templates (id)
                    ON UPDATE RESTRICT
                    ON DELETE RESTRICT
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT workflow_steps_step_number_bounded CHECK (
                    step_number BETWEEN 1 AND 50
                ),
                ADD CONSTRAINT workflow_steps_text_valid CHECK (
                    title = BTRIM(title)
                    AND CHAR_LENGTH(title) BETWEEN 1 AND 160
                    AND instruction = BTRIM(instruction)
                    AND CHAR_LENGTH(instruction) BETWEEN 1 AND 4000
                ),
                ADD CONSTRAINT workflow_steps_destination_known CHECK (
                    destination_action IS NULL
                    OR destination_action IN ('create_task', 'save_resource', 'use_tool', 'use_prompt')
                ),
                ADD CONSTRAINT workflow_steps_timestamp_order CHECK (
                    updated_at >= created_at
                )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE user_prompt_preferences
                ADD CONSTRAINT user_prompt_preferences_state_known CHECK (
                    state IN ('saved', 'dismissed')
                ),
                ADD CONSTRAINT user_prompt_preferences_timestamp_order CHECK (
                    updated_at >= created_at
                ),
                ADD CONSTRAINT user_prompt_preferences_user_foreign
                    FOREIGN KEY (user_id)
                    REFERENCES users (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT user_prompt_preferences_prompt_foreign
                    FOREIGN KEY (prompt_template_id)
                    REFERENCES prompt_templates (id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE user_prompt_copies
                ADD CONSTRAINT user_prompt_copies_count_bounded CHECK (
                    copy_count BETWEEN 1 AND 1000000
                ),
                ADD CONSTRAINT user_prompt_copies_time_order CHECK (
                    first_copied_at <= last_copied_at
                    AND updated_at >= created_at
                ),
                ADD CONSTRAINT user_prompt_copies_user_foreign
                    FOREIGN KEY (user_id)
                    REFERENCES users (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT user_prompt_copies_prompt_foreign
                    FOREIGN KEY (prompt_template_id)
                    REFERENCES prompt_templates (id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE user_workflow_preferences
                ADD CONSTRAINT user_workflow_preferences_state_known CHECK (
                    state IN ('saved', 'dismissed')
                ),
                ADD CONSTRAINT user_workflow_preferences_timestamp_order CHECK (
                    updated_at >= created_at
                ),
                ADD CONSTRAINT user_workflow_preferences_user_foreign
                    FOREIGN KEY (user_id)
                    REFERENCES users (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT user_workflow_preferences_workflow_foreign
                    FOREIGN KEY (workflow_recipe_id)
                    REFERENCES workflow_recipes (id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);
    }

    private function createIndexes(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX prompt_templates_category_lookup_idx
            ON prompt_templates (tool_category_id, id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX prompt_templates_admin_state_updated_cursor_idx
            ON prompt_templates (state, updated_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX prompt_templates_published_title_cursor_idx
            ON prompt_templates (LOWER(title), public_id)
            WHERE state = 'published' AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX prompt_templates_published_category_title_cursor_idx
            ON prompt_templates (tool_category_id, LOWER(title), public_id)
            WHERE state = 'published' AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX prompt_templates_published_reviewed_cursor_idx
            ON prompt_templates (last_reviewed_at DESC, public_id DESC)
            WHERE state = 'published' AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX prompt_template_tool_tool_prompt_idx
            ON prompt_template_tool (tool_id, prompt_template_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX workflow_recipes_category_lookup_idx
            ON workflow_recipes (tool_category_id, id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX workflow_recipes_admin_state_updated_cursor_idx
            ON workflow_recipes (state, updated_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX workflow_recipes_published_title_cursor_idx
            ON workflow_recipes (LOWER(title), public_id)
            WHERE state = 'published' AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX workflow_recipes_published_category_title_cursor_idx
            ON workflow_recipes (tool_category_id, LOWER(title), public_id)
            WHERE state = 'published' AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX workflow_recipes_published_reviewed_cursor_idx
            ON workflow_recipes (last_reviewed_at DESC, public_id DESC)
            WHERE state = 'published' AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX workflow_steps_recipe_order_idx
            ON workflow_steps (workflow_recipe_id, step_number)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX workflow_steps_tool_lookup_idx
            ON workflow_steps (tool_id)
            WHERE tool_id IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX workflow_steps_prompt_lookup_idx
            ON workflow_steps (prompt_template_id)
            WHERE prompt_template_id IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_prompt_preferences_user_state_updated_idx
            ON user_prompt_preferences (user_id, state, updated_at DESC, prompt_template_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_prompt_preferences_prompt_user_idx
            ON user_prompt_preferences (prompt_template_id, user_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_prompt_copies_prompt_user_idx
            ON user_prompt_copies (prompt_template_id, user_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_workflow_preferences_user_state_updated_idx
            ON user_workflow_preferences (user_id, state, updated_at DESC, workflow_recipe_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_workflow_preferences_workflow_user_idx
            ON user_workflow_preferences (workflow_recipe_id, user_id)
            SQL);
    }

    private function createIntegrityTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_prompt_template_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'prompt_templates_id_immutable',
                        MESSAGE = 'prompt_templates.id is immutable.';
                END IF;

                IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'prompt_templates_public_id_immutable',
                        MESSAGE = 'prompt_templates.public_id is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER prompt_templates_identity_immutable
            BEFORE UPDATE OF id, public_id ON prompt_templates
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_prompt_template_identity_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_enforce_prompt_template_transition()
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
                        CONSTRAINT = 'prompt_templates_state_transition_valid',
                        MESSAGE = 'prompt_templates.state transition is invalid.';
                END IF;

                IF NEW.state = 'published' AND OLD.state <> 'published'
                    AND NOT EXISTS (
                        SELECT 1
                        FROM prompt_template_tool
                        WHERE prompt_template_tool.prompt_template_id = NEW.id
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'prompt_templates_publication_requires_related_tool',
                        MESSAGE = 'A prompt template needs at least one related tool before publication.';
                END IF;

                content_changed := NEW.tool_category_id IS DISTINCT FROM OLD.tool_category_id
                    OR NEW.title IS DISTINCT FROM OLD.title
                    OR NEW.purpose IS DISTINCT FROM OLD.purpose
                    OR NEW.template_body IS DISTINCT FROM OLD.template_body
                    OR NEW.placeholders IS DISTINCT FROM OLD.placeholders
                    OR NEW.expected_output IS DISTINCT FROM OLD.expected_output
                    OR NEW.integrity_note IS DISTINCT FROM OLD.integrity_note
                    OR NEW.provenance IS DISTINCT FROM OLD.provenance;

                IF content_changed AND OLD.state <> 'draft'
                    AND NOT (
                        NEW.state = 'draft'
                        AND NEW.last_reviewed_at IS NULL
                        AND NEW.published_at IS NULL
                        AND NEW.archived_at IS NULL
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'prompt_templates_content_edit_invalidates_review',
                        MESSAGE = 'Reviewed prompt content must return to draft before it changes.';
                END IF;

                IF OLD.state = 'published' AND NEW.state = 'archived'
                    AND (
                        NEW.last_reviewed_at IS DISTINCT FROM OLD.last_reviewed_at
                        OR NEW.published_at IS DISTINCT FROM OLD.published_at
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'prompt_templates_archive_preserves_review',
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
                        CONSTRAINT = 'prompt_templates_review_metadata_immutable_in_state',
                        MESSAGE = 'Reviewed publication metadata is immutable without a lifecycle transition.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER prompt_templates_transition_valid
            BEFORE UPDATE OF
                tool_category_id,
                title,
                purpose,
                template_body,
                placeholders,
                expected_output,
                integrity_note,
                provenance,
                state,
                last_reviewed_at,
                published_at,
                archived_at
            ON prompt_templates
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_enforce_prompt_template_transition();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_workflow_recipe_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'workflow_recipes_id_immutable',
                        MESSAGE = 'workflow_recipes.id is immutable.';
                END IF;

                IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'workflow_recipes_public_id_immutable',
                        MESSAGE = 'workflow_recipes.public_id is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER workflow_recipes_identity_immutable
            BEFORE UPDATE OF id, public_id ON workflow_recipes
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_workflow_recipe_identity_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_enforce_workflow_recipe_transition()
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
                        CONSTRAINT = 'workflow_recipes_state_transition_valid',
                        MESSAGE = 'workflow_recipes.state transition is invalid.';
                END IF;

                IF NEW.state = 'published' AND OLD.state <> 'published'
                    AND NOT EXISTS (
                        SELECT 1
                        FROM workflow_steps
                        WHERE workflow_steps.workflow_recipe_id = NEW.id
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'workflow_recipes_publication_requires_step',
                        MESSAGE = 'A workflow recipe needs at least one ordered step before publication.';
                END IF;

                content_changed := NEW.tool_category_id IS DISTINCT FROM OLD.tool_category_id
                    OR NEW.title IS DISTINCT FROM OLD.title
                    OR NEW.goal IS DISTINCT FROM OLD.goal
                    OR NEW.expected_outcome IS DISTINCT FROM OLD.expected_outcome
                    OR NEW.integrity_note IS DISTINCT FROM OLD.integrity_note
                    OR NEW.provenance IS DISTINCT FROM OLD.provenance;

                IF content_changed AND OLD.state <> 'draft'
                    AND NOT (
                        NEW.state = 'draft'
                        AND NEW.last_reviewed_at IS NULL
                        AND NEW.published_at IS NULL
                        AND NEW.archived_at IS NULL
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'workflow_recipes_content_edit_invalidates_review',
                        MESSAGE = 'Reviewed workflow content must return to draft before it changes.';
                END IF;

                IF OLD.state = 'published' AND NEW.state = 'archived'
                    AND (
                        NEW.last_reviewed_at IS DISTINCT FROM OLD.last_reviewed_at
                        OR NEW.published_at IS DISTINCT FROM OLD.published_at
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'workflow_recipes_archive_preserves_review',
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
                        CONSTRAINT = 'workflow_recipes_review_metadata_immutable_in_state',
                        MESSAGE = 'Reviewed publication metadata is immutable without a lifecycle transition.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER workflow_recipes_transition_valid
            BEFORE UPDATE OF
                tool_category_id,
                title,
                goal,
                expected_outcome,
                integrity_note,
                provenance,
                state,
                last_reviewed_at,
                published_at,
                archived_at
            ON workflow_recipes
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_enforce_workflow_recipe_transition();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_prompt_preference_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.user_id IS DISTINCT FROM OLD.user_id
                    OR NEW.prompt_template_id IS DISTINCT FROM OLD.prompt_template_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'user_prompt_preferences_identity_immutable',
                        MESSAGE = 'user_prompt_preferences identity is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER user_prompt_preferences_identity_immutable
            BEFORE UPDATE OF id, user_id, prompt_template_id ON user_prompt_preferences
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_prompt_preference_identity_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_prompt_copy_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.user_id IS DISTINCT FROM OLD.user_id
                    OR NEW.prompt_template_id IS DISTINCT FROM OLD.prompt_template_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'user_prompt_copies_identity_immutable',
                        MESSAGE = 'user_prompt_copies identity is immutable.';
                END IF;

                IF NEW.copy_count < OLD.copy_count THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'user_prompt_copies_count_monotonic',
                        MESSAGE = 'user_prompt_copies.copy_count cannot decrease.';
                END IF;

                IF NEW.first_copied_at IS DISTINCT FROM OLD.first_copied_at THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'user_prompt_copies_first_copy_immutable',
                        MESSAGE = 'user_prompt_copies.first_copied_at is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER user_prompt_copies_identity_immutable
            BEFORE UPDATE OF id, user_id, prompt_template_id, copy_count, first_copied_at ON user_prompt_copies
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_prompt_copy_identity_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_workflow_preference_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.user_id IS DISTINCT FROM OLD.user_id
                    OR NEW.workflow_recipe_id IS DISTINCT FROM OLD.workflow_recipe_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'user_workflow_preferences_identity_immutable',
                        MESSAGE = 'user_workflow_preferences identity is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER user_workflow_preferences_identity_immutable
            BEFORE UPDATE OF id, user_id, workflow_recipe_id ON user_workflow_preferences
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_workflow_preference_identity_update();
            SQL);
    }
};
