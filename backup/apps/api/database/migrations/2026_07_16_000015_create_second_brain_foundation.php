<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'research_topic_sources',
        'research_topics',
        'knowledge_links',
        'collection_knowledge_items',
        'knowledge_item_tags',
        'knowledge_tags',
        'knowledge_notes',
        'knowledge_items',
        'collections',
    ];

    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 1000)->nullable();
            $table->string('kind', 16)->default('general');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'id'], 'collections_owner_id_unique');
        });

        Schema::create('knowledge_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_type', 8)->default('none');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('title', 200);
            $table->string('summary', 4000)->nullable();
            $table->string('authors', 500)->nullable();
            $table->smallInteger('published_year')->nullable();
            $table->string('venue', 200)->nullable();
            $table->string('doi', 255)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'id'], 'knowledge_items_owner_id_unique');
        });

        Schema::create('knowledge_notes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('knowledge_item_id');
            $table->string('body', 8000);
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);
        });

        Schema::create('knowledge_tags', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 60);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'id'], 'knowledge_tags_owner_id_unique');
        });

        Schema::create('knowledge_item_tags', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('knowledge_item_id');
            $table->unsignedBigInteger('knowledge_tag_id');
            $table->timestampTz('created_at', 0)->useCurrent();

            $table->unique(['knowledge_item_id', 'knowledge_tag_id'], 'knowledge_item_tags_pair_unique');
        });

        Schema::create('collection_knowledge_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('collection_id');
            $table->unsignedBigInteger('knowledge_item_id');
            $table->timestampTz('created_at', 0)->useCurrent();

            $table->unique(['collection_id', 'knowledge_item_id'], 'collection_knowledge_items_pair_unique');
        });

        Schema::create('knowledge_links', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('from_item_id');
            $table->unsignedBigInteger('to_item_id');
            $table->string('relation_type', 16)->default('related');
            $table->timestampTz('created_at', 0)->useCurrent();

            $table->unique(['from_item_id', 'to_item_id'], 'knowledge_links_pair_unique');
        });

        Schema::create('research_topics', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('description', 2000)->nullable();
            $table->jsonb('keywords')->default('[]');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'id'], 'research_topics_owner_id_unique');
        });

        Schema::create('research_topic_sources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('research_topic_id');
            $table->unsignedBigInteger('knowledge_item_id');
            $table->string('reading_status', 8)->default('to_read');
            $table->timestampsTz(0);

            $table->unique(['research_topic_id', 'knowledge_item_id'], 'research_topic_sources_pair_unique');
        });

        foreach (['collections', 'knowledge_items', 'knowledge_notes', 'knowledge_tags', 'knowledge_links', 'research_topics'] as $tableName) {
            DB::statement("ALTER TABLE {$tableName} ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()");
        }

        $this->addConstraints();
        $this->createIndexes();
        $this->createIntegrityTriggers();
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (Schema::hasTable($tableName) && DB::table($tableName)->exists()) {
                throw new RuntimeException(
                    'Cannot roll back the Second Brain foundation while knowledge or research data exists.',
                );
            }
        }

        foreach (self::TABLES as $tableName) {
            Schema::dropIfExists($tableName);
        }

        DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_brain_identity_update()');
        DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_knowledge_source_update()');
        DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_note_parent_update()');
    }

    private function addConstraints(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE collections
                ADD CONSTRAINT collections_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT collections_name_not_blank CHECK (BTRIM(name) <> ''),
                ADD CONSTRAINT collections_description_not_blank CHECK (
                    description IS NULL OR BTRIM(description) <> ''
                ),
                ADD CONSTRAINT collections_kind_known CHECK (
                    kind IN ('course', 'project', 'research', 'goal', 'general')
                ),
                ADD CONSTRAINT collections_version_positive CHECK (version >= 1)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE knowledge_items
                ADD CONSTRAINT knowledge_items_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT knowledge_items_title_not_blank CHECK (BTRIM(title) <> ''),
                ADD CONSTRAINT knowledge_items_text_not_blank CHECK (
                    (summary IS NULL OR BTRIM(summary) <> '')
                    AND (authors IS NULL OR BTRIM(authors) <> '')
                    AND (venue IS NULL OR BTRIM(venue) <> '')
                    AND (doi IS NULL OR BTRIM(doi) <> '')
                ),
                ADD CONSTRAINT knowledge_items_source_consistent CHECK (
                    (source_type = 'resource' AND resource_id IS NOT NULL AND source_url IS NULL)
                    OR (source_type = 'link' AND resource_id IS NULL AND source_url ~ '^https://')
                    OR (source_type = 'none' AND resource_id IS NULL AND source_url IS NULL)
                ),
                ADD CONSTRAINT knowledge_items_published_year_sane CHECK (
                    published_year IS NULL OR published_year BETWEEN 1000 AND 2100
                ),
                ADD CONSTRAINT knowledge_items_version_positive CHECK (version >= 1),
                ADD CONSTRAINT knowledge_items_owner_resource_foreign
                    FOREIGN KEY (user_id, resource_id)
                    REFERENCES resources (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE knowledge_items
                ADD COLUMN search_vector tsvector
                GENERATED ALWAYS AS (
                    to_tsvector(
                        'simple',
                        title
                        || ' ' || COALESCE(summary, '')
                        || ' ' || COALESCE(authors, '')
                        || ' ' || COALESCE(venue, '')
                    )
                ) STORED
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE knowledge_notes
                ADD CONSTRAINT knowledge_notes_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT knowledge_notes_body_not_blank CHECK (BTRIM(body) <> ''),
                ADD CONSTRAINT knowledge_notes_version_positive CHECK (version >= 1),
                ADD CONSTRAINT knowledge_notes_owner_item_foreign
                    FOREIGN KEY (user_id, knowledge_item_id)
                    REFERENCES knowledge_items (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE knowledge_tags
                ADD CONSTRAINT knowledge_tags_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT knowledge_tags_name_not_blank CHECK (BTRIM(name) <> '')
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE knowledge_item_tags
                ADD CONSTRAINT knowledge_item_tags_owner_item_foreign
                    FOREIGN KEY (user_id, knowledge_item_id)
                    REFERENCES knowledge_items (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT knowledge_item_tags_owner_tag_foreign
                    FOREIGN KEY (user_id, knowledge_tag_id)
                    REFERENCES knowledge_tags (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE collection_knowledge_items
                ADD CONSTRAINT collection_knowledge_items_owner_collection_foreign
                    FOREIGN KEY (user_id, collection_id)
                    REFERENCES collections (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT collection_knowledge_items_owner_item_foreign
                    FOREIGN KEY (user_id, knowledge_item_id)
                    REFERENCES knowledge_items (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE knowledge_links
                ADD CONSTRAINT knowledge_links_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT knowledge_links_relation_known CHECK (
                    relation_type IN ('related', 'supports', 'contradicts', 'builds_on')
                ),
                ADD CONSTRAINT knowledge_links_not_self CHECK (from_item_id <> to_item_id),
                ADD CONSTRAINT knowledge_links_owner_from_foreign
                    FOREIGN KEY (user_id, from_item_id)
                    REFERENCES knowledge_items (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT knowledge_links_owner_to_foreign
                    FOREIGN KEY (user_id, to_item_id)
                    REFERENCES knowledge_items (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE research_topics
                ADD CONSTRAINT research_topics_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT research_topics_title_not_blank CHECK (BTRIM(title) <> ''),
                ADD CONSTRAINT research_topics_description_not_blank CHECK (
                    description IS NULL OR BTRIM(description) <> ''
                ),
                ADD CONSTRAINT research_topics_keywords_shape CHECK (
                    jsonb_typeof(keywords) = 'array' AND jsonb_array_length(keywords) <= 20
                ),
                ADD CONSTRAINT research_topics_version_positive CHECK (version >= 1)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE research_topic_sources
                ADD CONSTRAINT research_topic_sources_reading_status_known CHECK (
                    reading_status IN ('to_read', 'reading', 'read')
                ),
                ADD CONSTRAINT research_topic_sources_owner_topic_foreign
                    FOREIGN KEY (user_id, research_topic_id)
                    REFERENCES research_topics (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT research_topic_sources_owner_item_foreign
                    FOREIGN KEY (user_id, knowledge_item_id)
                    REFERENCES knowledge_items (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);
    }

    private function createIndexes(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX collections_owner_name_unique
            ON collections (user_id, LOWER(name))
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX knowledge_tags_owner_name_unique
            ON knowledge_tags (user_id, LOWER(name))
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX collections_owner_updated_cursor_idx
            ON collections (user_id, updated_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX collections_owner_created_cursor_idx
            ON collections (user_id, created_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX knowledge_items_owner_updated_cursor_idx
            ON knowledge_items (user_id, updated_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX knowledge_items_owner_created_cursor_idx
            ON knowledge_items (user_id, created_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX knowledge_items_search_vector_idx
            ON knowledge_items USING GIN (search_vector)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX knowledge_items_owner_title_prefix_idx
            ON knowledge_items (user_id, LOWER(title) text_pattern_ops)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX knowledge_items_resource_lookup_idx
            ON knowledge_items (resource_id)
            WHERE resource_id IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX knowledge_notes_item_created_idx
            ON knowledge_notes (knowledge_item_id, created_at DESC, id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX knowledge_item_tags_tag_lookup_idx
            ON knowledge_item_tags (knowledge_tag_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX collection_knowledge_items_item_lookup_idx
            ON collection_knowledge_items (knowledge_item_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX knowledge_links_to_item_lookup_idx
            ON knowledge_links (to_item_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX research_topics_owner_updated_cursor_idx
            ON research_topics (user_id, updated_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX research_topics_owner_created_cursor_idx
            ON research_topics (user_id, created_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX research_topic_sources_item_lookup_idx
            ON research_topic_sources (knowledge_item_id)
            SQL);
    }

    private function createIntegrityTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_brain_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'brain_public_id_immutable',
                        MESSAGE = TG_TABLE_NAME || '.public_id is immutable.';
                END IF;

                IF NEW.user_id IS DISTINCT FROM OLD.user_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'brain_owner_immutable',
                        MESSAGE = TG_TABLE_NAME || '.user_id is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER collections_identity_immutable
            BEFORE UPDATE OF public_id, user_id ON collections
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_brain_identity_update();

            CREATE TRIGGER knowledge_items_identity_immutable
            BEFORE UPDATE OF public_id, user_id ON knowledge_items
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_brain_identity_update();

            CREATE TRIGGER knowledge_notes_identity_immutable
            BEFORE UPDATE OF public_id, user_id ON knowledge_notes
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_brain_identity_update();

            CREATE TRIGGER knowledge_tags_identity_immutable
            BEFORE UPDATE OF public_id, user_id ON knowledge_tags
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_brain_identity_update();

            CREATE TRIGGER research_topics_identity_immutable
            BEFORE UPDATE OF public_id, user_id ON research_topics
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_brain_identity_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_knowledge_source_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.source_type IS DISTINCT FROM OLD.source_type
                    OR NEW.resource_id IS DISTINCT FROM OLD.resource_id
                    OR NEW.source_url IS DISTINCT FROM OLD.source_url THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'knowledge_items_source_immutable',
                        MESSAGE = 'knowledge_items source provenance is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER knowledge_items_source_immutable
            BEFORE UPDATE OF source_type, resource_id, source_url ON knowledge_items
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_knowledge_source_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_note_parent_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.knowledge_item_id IS DISTINCT FROM OLD.knowledge_item_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'knowledge_notes_parent_immutable',
                        MESSAGE = 'knowledge_notes.knowledge_item_id is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER knowledge_notes_parent_immutable
            BEFORE UPDATE OF knowledge_item_id ON knowledge_notes
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_note_parent_update();
            SQL);
    }
};
