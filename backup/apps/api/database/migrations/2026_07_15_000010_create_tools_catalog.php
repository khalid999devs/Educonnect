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
        Schema::create('tool_categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->string('description', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz(0);
        });

        Schema::create('tools', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('tool_category_id');
            $table->string('name', 160);
            $table->text('purpose')->nullable();
            $table->text('selection_reason')->nullable();
            $table->jsonb('use_cases')->nullable();
            $table->text('usage_guidance')->nullable();
            $table->text('limitations')->nullable();
            $table->text('cost_note')->nullable();
            $table->text('privacy_note')->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->text('provenance')->nullable();
            $table->string('state', 16)->default('draft');
            $table->timestampTz('last_reviewed_at', 0)->nullable();
            $table->timestampTz('published_at', 0)->nullable();
            $table->timestampTz('archived_at', 0)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);
        });

        Schema::create('user_tool_preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tool_id');
            $table->string('state', 16);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'tool_id'], 'user_tool_preferences_user_tool_unique');
        });

        DB::statement('ALTER TABLE tool_categories ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');
        DB::statement('ALTER TABLE tools ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');

        $this->createUseCasesValidationFunction();
        $this->addConstraints();
        $this->createIndexes();
        $this->createIntegrityTriggers();
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            if (Schema::hasTable('user_tool_preferences')) {
                DB::statement('LOCK TABLE user_tool_preferences IN ACCESS EXCLUSIVE MODE');
            }

            if (Schema::hasTable('tools')) {
                DB::statement('LOCK TABLE tools IN ACCESS EXCLUSIVE MODE');
            }

            if (Schema::hasTable('tool_categories')) {
                DB::statement('LOCK TABLE tool_categories IN ACCESS EXCLUSIVE MODE');
            }

            $hasCatalogRows = (Schema::hasTable('user_tool_preferences') && DB::table('user_tool_preferences')->exists())
                || (Schema::hasTable('tools') && DB::table('tools')->exists())
                || (Schema::hasTable('tool_categories') && DB::table('tool_categories')->exists());

            if ($hasCatalogRows) {
                throw new RuntimeException(
                    'Cannot roll back the tools catalog while category, tool, or user-preference data exists.',
                );
            }

            Schema::dropIfExists('user_tool_preferences');
            Schema::dropIfExists('tools');
            Schema::dropIfExists('tool_categories');

            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_tool_preference_identity_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_enforce_tool_transition()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_tool_identity_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_tool_category_identity_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_tool_use_cases_valid(JSONB)');
        }, 3);
    }

    private function createUseCasesValidationFunction(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_tool_use_cases_valid(candidate JSONB)
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
                        OR CHAR_LENGTH(item_text) NOT BETWEEN 1 AND 500 THEN
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
            ALTER TABLE tool_categories
                ADD CONSTRAINT tool_categories_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT tool_categories_slug_format CHECK (
                    slug = BTRIM(slug)
                    AND slug = LOWER(slug)
                    AND CHAR_LENGTH(slug) BETWEEN 2 AND 80
                    AND slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'
                ),
                ADD CONSTRAINT tool_categories_text_valid CHECK (
                    name = BTRIM(name)
                    AND CHAR_LENGTH(name) BETWEEN 1 AND 120
                    AND (
                        description IS NULL
                        OR (
                            description = BTRIM(description)
                            AND CHAR_LENGTH(description) BETWEEN 1 AND 1000
                        )
                    )
                ),
                ADD CONSTRAINT tool_categories_sort_order_bounded CHECK (
                    sort_order BETWEEN 0 AND 65535
                ),
                ADD CONSTRAINT tool_categories_timestamp_order CHECK (
                    updated_at >= created_at
                )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE tools
                ADD CONSTRAINT tools_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT tools_category_foreign
                    FOREIGN KEY (tool_category_id)
                    REFERENCES tool_categories (id)
                    ON UPDATE RESTRICT
                    ON DELETE RESTRICT
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT tools_name_valid CHECK (
                    name = BTRIM(name)
                    AND CHAR_LENGTH(name) BETWEEN 1 AND 160
                ),
                ADD CONSTRAINT tools_publication_text_valid CHECK (
                    (
                        purpose IS NULL
                        OR (
                            purpose = BTRIM(purpose)
                            AND CHAR_LENGTH(purpose) BETWEEN 1 AND 2000
                        )
                    )
                    AND (
                        selection_reason IS NULL
                        OR (
                            selection_reason = BTRIM(selection_reason)
                            AND CHAR_LENGTH(selection_reason) BETWEEN 1 AND 2000
                        )
                    )
                    AND (
                        usage_guidance IS NULL
                        OR (
                            usage_guidance = BTRIM(usage_guidance)
                            AND CHAR_LENGTH(usage_guidance) BETWEEN 1 AND 4000
                        )
                    )
                    AND (
                        limitations IS NULL
                        OR (
                            limitations = BTRIM(limitations)
                            AND CHAR_LENGTH(limitations) BETWEEN 1 AND 4000
                        )
                    )
                    AND (
                        cost_note IS NULL
                        OR (
                            cost_note = BTRIM(cost_note)
                            AND CHAR_LENGTH(cost_note) BETWEEN 1 AND 2000
                        )
                    )
                    AND (
                        privacy_note IS NULL
                        OR (
                            privacy_note = BTRIM(privacy_note)
                            AND CHAR_LENGTH(privacy_note) BETWEEN 1 AND 2000
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
                ADD CONSTRAINT tools_use_cases_valid CHECK (
                    use_cases IS NULL OR educonnect_tool_use_cases_valid(use_cases)
                ),
                ADD CONSTRAINT tools_external_url_valid CHECK (
                    external_url IS NULL
                    OR (
                        external_url = BTRIM(external_url)
                        AND external_url ~ '^https://([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?([/?#][^[:space:]]*)?$'
                        AND external_url !~ '[[:cntrl:]]'
                    )
                ),
                ADD CONSTRAINT tools_state_known CHECK (
                    state IN ('draft', 'in_review', 'published', 'archived')
                ),
                ADD CONSTRAINT tools_published_content_complete CHECK (
                    state NOT IN ('published', 'archived')
                    OR (
                        purpose IS NOT NULL
                        AND selection_reason IS NOT NULL
                        AND use_cases IS NOT NULL
                        AND usage_guidance IS NOT NULL
                        AND limitations IS NOT NULL
                        AND cost_note IS NOT NULL
                        AND privacy_note IS NOT NULL
                        AND external_url IS NOT NULL
                        AND provenance IS NOT NULL
                    )
                ),
                ADD CONSTRAINT tools_lifecycle_consistent CHECK (
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
                ADD CONSTRAINT tools_lifecycle_time_order CHECK (
                    (last_reviewed_at IS NULL OR published_at IS NULL OR last_reviewed_at <= published_at)
                    AND (published_at IS NULL OR archived_at IS NULL OR published_at <= archived_at)
                    AND updated_at >= created_at
                ),
                ADD CONSTRAINT tools_version_positive CHECK (version >= 1)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE user_tool_preferences
                ADD CONSTRAINT user_tool_preferences_state_known CHECK (
                    state IN ('saved', 'dismissed')
                ),
                ADD CONSTRAINT user_tool_preferences_timestamp_order CHECK (
                    updated_at >= created_at
                ),
                ADD CONSTRAINT user_tool_preferences_user_foreign
                    FOREIGN KEY (user_id)
                    REFERENCES users (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT user_tool_preferences_tool_foreign
                    FOREIGN KEY (tool_id)
                    REFERENCES tools (id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);
    }

    private function createIndexes(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX tool_categories_catalog_order_idx
            ON tool_categories (sort_order, name, public_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX tools_category_lookup_idx
            ON tools (tool_category_id, id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX tools_admin_state_updated_cursor_idx
            ON tools (state, updated_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX tools_published_name_cursor_idx
            ON tools (LOWER(name), public_id)
            WHERE state = 'published' AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX tools_published_category_name_cursor_idx
            ON tools (tool_category_id, LOWER(name), public_id)
            WHERE state = 'published' AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX tools_published_reviewed_cursor_idx
            ON tools (last_reviewed_at DESC, public_id DESC)
            WHERE state = 'published' AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_tool_preferences_user_state_updated_idx
            ON user_tool_preferences (user_id, state, updated_at DESC, tool_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX user_tool_preferences_tool_user_idx
            ON user_tool_preferences (tool_id, user_id)
            SQL);
    }

    private function createIntegrityTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_tool_category_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'tool_categories_id_immutable',
                        MESSAGE = 'tool_categories.id is immutable.';
                END IF;

                IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'tool_categories_public_id_immutable',
                        MESSAGE = 'tool_categories.public_id is immutable.';
                END IF;

                IF NEW.slug IS DISTINCT FROM OLD.slug THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'tool_categories_slug_immutable',
                        MESSAGE = 'tool_categories.slug is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER tool_categories_identity_immutable
            BEFORE UPDATE OF id, public_id, slug ON tool_categories
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_tool_category_identity_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_tool_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'tools_id_immutable',
                        MESSAGE = 'tools.id is immutable.';
                END IF;

                IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'tools_public_id_immutable',
                        MESSAGE = 'tools.public_id is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER tools_identity_immutable
            BEFORE UPDATE OF id, public_id ON tools
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_tool_identity_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_enforce_tool_transition()
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
                        CONSTRAINT = 'tools_state_transition_valid',
                        MESSAGE = 'tools.state transition is invalid.';
                END IF;

                content_changed := NEW.tool_category_id IS DISTINCT FROM OLD.tool_category_id
                    OR NEW.name IS DISTINCT FROM OLD.name
                    OR NEW.purpose IS DISTINCT FROM OLD.purpose
                    OR NEW.selection_reason IS DISTINCT FROM OLD.selection_reason
                    OR NEW.use_cases IS DISTINCT FROM OLD.use_cases
                    OR NEW.usage_guidance IS DISTINCT FROM OLD.usage_guidance
                    OR NEW.limitations IS DISTINCT FROM OLD.limitations
                    OR NEW.cost_note IS DISTINCT FROM OLD.cost_note
                    OR NEW.privacy_note IS DISTINCT FROM OLD.privacy_note
                    OR NEW.external_url IS DISTINCT FROM OLD.external_url
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
                        CONSTRAINT = 'tools_content_edit_invalidates_review',
                        MESSAGE = 'Reviewed tool content must return to draft before it changes.';
                END IF;

                IF OLD.state = 'published' AND NEW.state = 'archived'
                    AND (
                        NEW.last_reviewed_at IS DISTINCT FROM OLD.last_reviewed_at
                        OR NEW.published_at IS DISTINCT FROM OLD.published_at
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'tools_archive_preserves_review',
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
                        CONSTRAINT = 'tools_review_metadata_immutable_in_state',
                        MESSAGE = 'Reviewed publication metadata is immutable without a lifecycle transition.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER tools_transition_valid
            BEFORE UPDATE OF
                tool_category_id,
                name,
                purpose,
                selection_reason,
                use_cases,
                usage_guidance,
                limitations,
                cost_note,
                privacy_note,
                external_url,
                provenance,
                state,
                last_reviewed_at,
                published_at,
                archived_at
            ON tools
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_enforce_tool_transition();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_tool_preference_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.user_id IS DISTINCT FROM OLD.user_id
                    OR NEW.tool_id IS DISTINCT FROM OLD.tool_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'user_tool_preferences_identity_immutable',
                        MESSAGE = 'user_tool_preferences identity is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER user_tool_preferences_identity_immutable
            BEFORE UPDATE OF id, user_id, tool_id ON user_tool_preferences
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_tool_preference_identity_update();
            SQL);
    }
};
