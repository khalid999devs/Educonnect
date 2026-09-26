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
        'mentor_requests',
        'mentor_profiles',
        'content_reports',
        'community_comments',
        'community_posts',
        'community_memberships',
        'communities',
    ];

    /** @var list<string> */
    private const PUBLIC_ID_TABLES = [
        'communities',
        'community_memberships',
        'community_posts',
        'community_comments',
        'content_reports',
        'mentor_profiles',
        'mentor_requests',
    ];

    public function up(): void
    {
        Schema::create('communities', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('slug', 80);
            $table->string('name', 120);
            $table->string('summary', 280);
            $table->string('description', 2000)->nullable();
            $table->string('topic', 80)->nullable();
            $table->string('visibility', 12)->default('published');
            $table->boolean('is_seeded')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);
        });

        Schema::create('community_memberships', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 12)->default('member');
            $table->timestampsTz(0);

            $table->unique(['community_id', 'user_id'], 'community_memberships_pair_unique');
            $table->index(['user_id', 'community_id'], 'community_memberships_user_idx');
        });

        Schema::create('community_posts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 160)->nullable();
            $table->string('body', 5000);
            $table->unsignedBigInteger('shared_resource_id')->nullable();
            $table->string('moderation_state', 20)->default('visible');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);
        });

        Schema::create('community_comments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('body', 2000);
            $table->string('moderation_state', 20)->default('visible');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);
        });

        Schema::create('content_reports', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->unsignedBigInteger('post_id')->nullable();
            $table->unsignedBigInteger('comment_id')->nullable();
            $table->string('reason', 16);
            $table->string('detail', 1000)->nullable();
            $table->string('status', 12)->default('open');
            $table->string('resolution_note', 2000)->nullable();
            $table->unsignedBigInteger('handled_by_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('handled_at', 0)->nullable();
            $table->timestampsTz(0);

            $table->foreign('post_id')->references('id')->on('community_posts')->cascadeOnDelete();
            $table->foreign('comment_id')->references('id')->on('community_comments')->cascadeOnDelete();
            $table->foreign('handled_by_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['community_id', 'status', 'created_at'], 'content_reports_scope_idx');
        });

        Schema::create('mentor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('headline', 160);
            $table->string('bio', 2000);
            $table->jsonb('expertise')->default('[]');
            $table->string('availability_note', 280)->nullable();
            $table->string('verification_state', 12)->default('unverified');
            $table->boolean('is_accepting_requests')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'id'], 'mentor_profiles_owner_id_unique');
        });

        Schema::create('mentor_requests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentor_profile_id')->constrained('mentor_profiles')->cascadeOnDelete();
            $table->string('subject', 160);
            $table->string('message', 2000);
            $table->unsignedBigInteger('context_course_id')->nullable();
            $table->string('status', 12)->default('open');
            $table->string('response_note', 2000)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('responded_at', 0)->nullable();
            $table->timestampsTz(0);

            $table->index(['mentor_profile_id', 'status', 'created_at'], 'mentor_requests_incoming_idx');
            $table->index(['requester_id', 'created_at'], 'mentor_requests_sent_idx');
        });

        foreach (self::PUBLIC_ID_TABLES as $tableName) {
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
                    'Cannot roll back the community and mentor foundation while community or mentor data exists.',
                );
            }
        }

        foreach (self::TABLES as $tableName) {
            Schema::dropIfExists($tableName);
        }

        DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_community_public_id_update()');
    }

    private function addConstraints(): void
    {
        $publicIdFormat = "public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'";

        DB::statement(<<<SQL
            ALTER TABLE communities
                ADD CONSTRAINT communities_public_id_format CHECK ({$publicIdFormat}),
                ADD CONSTRAINT communities_slug_format CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'),
                ADD CONSTRAINT communities_name_not_blank CHECK (BTRIM(name) <> ''),
                ADD CONSTRAINT communities_summary_not_blank CHECK (BTRIM(summary) <> ''),
                ADD CONSTRAINT communities_description_not_blank CHECK (
                    description IS NULL OR BTRIM(description) <> ''
                ),
                ADD CONSTRAINT communities_topic_not_blank CHECK (topic IS NULL OR BTRIM(topic) <> ''),
                ADD CONSTRAINT communities_visibility_known CHECK (visibility IN ('published', 'archived')),
                ADD CONSTRAINT communities_version_positive CHECK (version >= 1)
            SQL);

        DB::statement(<<<SQL
            ALTER TABLE community_memberships
                ADD CONSTRAINT community_memberships_public_id_format CHECK ({$publicIdFormat}),
                ADD CONSTRAINT community_memberships_role_known CHECK (role IN ('member', 'moderator'))
            SQL);

        DB::statement(<<<SQL
            ALTER TABLE community_posts
                ADD CONSTRAINT community_posts_public_id_format CHECK ({$publicIdFormat}),
                ADD CONSTRAINT community_posts_title_not_blank CHECK (title IS NULL OR BTRIM(title) <> ''),
                ADD CONSTRAINT community_posts_body_not_blank CHECK (BTRIM(body) <> ''),
                ADD CONSTRAINT community_posts_moderation_state_known CHECK (
                    moderation_state IN ('visible', 'hidden_by_moderator', 'removed_by_author')
                ),
                ADD CONSTRAINT community_posts_version_positive CHECK (version >= 1),
                ADD CONSTRAINT community_posts_owner_resource_foreign
                    FOREIGN KEY (author_id, shared_resource_id)
                    REFERENCES resources (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<SQL
            ALTER TABLE community_comments
                ADD CONSTRAINT community_comments_public_id_format CHECK ({$publicIdFormat}),
                ADD CONSTRAINT community_comments_body_not_blank CHECK (BTRIM(body) <> ''),
                ADD CONSTRAINT community_comments_moderation_state_known CHECK (
                    moderation_state IN ('visible', 'hidden_by_moderator', 'removed_by_author')
                ),
                ADD CONSTRAINT community_comments_version_positive CHECK (version >= 1)
            SQL);

        DB::statement(<<<SQL
            ALTER TABLE content_reports
                ADD CONSTRAINT content_reports_public_id_format CHECK ({$publicIdFormat}),
                ADD CONSTRAINT content_reports_subject_exactly_one CHECK (
                    (post_id IS NOT NULL)::int + (comment_id IS NOT NULL)::int = 1
                ),
                ADD CONSTRAINT content_reports_reason_known CHECK (
                    reason IN ('spam', 'harassment', 'off_topic', 'safety', 'other')
                ),
                ADD CONSTRAINT content_reports_detail_not_blank CHECK (detail IS NULL OR BTRIM(detail) <> ''),
                ADD CONSTRAINT content_reports_status_known CHECK (
                    status IN ('open', 'reviewing', 'actioned', 'dismissed')
                ),
                ADD CONSTRAINT content_reports_resolution_consistent CHECK (
                    (status IN ('open', 'reviewing') AND handled_by_id IS NULL AND handled_at IS NULL)
                    OR (status IN ('actioned', 'dismissed') AND handled_by_id IS NOT NULL AND handled_at IS NOT NULL)
                ),
                ADD CONSTRAINT content_reports_note_not_blank CHECK (
                    resolution_note IS NULL OR BTRIM(resolution_note) <> ''
                ),
                ADD CONSTRAINT content_reports_version_positive CHECK (version >= 1)
            SQL);

        DB::statement(<<<SQL
            ALTER TABLE mentor_profiles
                ADD CONSTRAINT mentor_profiles_public_id_format CHECK ({$publicIdFormat}),
                ADD CONSTRAINT mentor_profiles_headline_not_blank CHECK (BTRIM(headline) <> ''),
                ADD CONSTRAINT mentor_profiles_bio_not_blank CHECK (BTRIM(bio) <> ''),
                ADD CONSTRAINT mentor_profiles_expertise_is_array CHECK (jsonb_typeof(expertise) = 'array'),
                ADD CONSTRAINT mentor_profiles_availability_not_blank CHECK (
                    availability_note IS NULL OR BTRIM(availability_note) <> ''
                ),
                ADD CONSTRAINT mentor_profiles_verification_known CHECK (
                    verification_state IN ('unverified', 'verified')
                ),
                ADD CONSTRAINT mentor_profiles_version_positive CHECK (version >= 1)
            SQL);

        DB::statement(<<<SQL
            ALTER TABLE mentor_requests
                ADD CONSTRAINT mentor_requests_public_id_format CHECK ({$publicIdFormat}),
                ADD CONSTRAINT mentor_requests_subject_not_blank CHECK (BTRIM(subject) <> ''),
                ADD CONSTRAINT mentor_requests_message_not_blank CHECK (BTRIM(message) <> ''),
                ADD CONSTRAINT mentor_requests_status_known CHECK (
                    status IN ('open', 'accepted', 'declined', 'withdrawn', 'completed')
                ),
                ADD CONSTRAINT mentor_requests_note_not_blank CHECK (
                    response_note IS NULL OR BTRIM(response_note) <> ''
                ),
                ADD CONSTRAINT mentor_requests_responded_consistent CHECK (
                    (status = 'open' AND responded_at IS NULL)
                    OR (status <> 'open' AND responded_at IS NOT NULL)
                ),
                ADD CONSTRAINT mentor_requests_version_positive CHECK (version >= 1),
                ADD CONSTRAINT mentor_requests_context_course_foreign
                    FOREIGN KEY (requester_id, context_course_id)
                    REFERENCES courses (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);
    }

    private function createIndexes(): void
    {
        DB::statement('CREATE UNIQUE INDEX communities_slug_unique ON communities (slug)');
        DB::statement('CREATE INDEX communities_visibility_created_idx ON communities (visibility, created_at DESC, public_id DESC)');

        DB::statement('CREATE INDEX community_posts_feed_idx ON community_posts (community_id, created_at DESC, public_id DESC)');
        DB::statement('CREATE INDEX community_posts_author_idx ON community_posts (author_id, created_at DESC, public_id DESC)');

        DB::statement('CREATE INDEX community_comments_thread_idx ON community_comments (post_id, created_at ASC, public_id ASC)');

        DB::statement(
            'CREATE UNIQUE INDEX content_reports_reporter_post_unique ON content_reports (reporter_id, post_id) WHERE post_id IS NOT NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX content_reports_reporter_comment_unique ON content_reports (reporter_id, comment_id) WHERE comment_id IS NOT NULL'
        );

        DB::statement('CREATE INDEX mentor_profiles_discovery_idx ON mentor_profiles (verification_state, created_at DESC, public_id DESC)');
    }

    private function createIntegrityTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_community_public_id_update()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
                    RAISE EXCEPTION 'The public identifier cannot be modified.'
                        USING ERRCODE = '23514', CONSTRAINT = TG_TABLE_NAME || '_public_id_immutable';
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        foreach (self::PUBLIC_ID_TABLES as $tableName) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$tableName}_reject_public_id_update
                    BEFORE UPDATE OF public_id ON {$tableName}
                    FOR EACH ROW
                    EXECUTE FUNCTION educonnect_reject_community_public_id_update();
            SQL);
        }
    }
};
