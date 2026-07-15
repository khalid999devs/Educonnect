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
        Schema::create('resources', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('kind', 8);
            $table->string('title', 160);
            $table->string('description', 2000)->nullable();
            $table->string('topic_label', 120)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'id'], 'resources_owner_id_unique');
        });

        Schema::create('stored_files', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('resource_id')->unique();
            $table->string('original_name', 255);
            $table->string('declared_mime_type', 100);
            $table->unsignedBigInteger('expected_size');
            $table->char('sha256', 64);
            $table->string('upload_key', 96)->nullable()->unique();
            $table->string('object_key', 96)->unique();
            $table->string('verified_mime_type', 100)->nullable();
            $table->unsignedBigInteger('verified_size')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestampTz('upload_expires_at', 0);
            $table->timestampTz('cleanup_after', 0)->nullable();
            $table->timestampTz('cleanup_started_at', 0)->nullable();
            $table->unsignedSmallInteger('cleanup_failures')->default(0);
            $table->timestampTz('purge_ready_at', 0)->nullable();
            $table->timestampTz('ready_at', 0)->nullable();
            $table->timestampsTz(0);
        });

        DB::statement('ALTER TABLE resources ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');
        DB::statement('ALTER TABLE stored_files ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');

        DB::statement(<<<'SQL'
            ALTER TABLE resources
                ADD CONSTRAINT resources_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT resources_kind_known CHECK (
                    kind IN ('link', 'file')
                ),
                ADD CONSTRAINT resources_text_not_blank CHECK (
                    BTRIM(title) <> ''
                    AND (description IS NULL OR BTRIM(description) <> '')
                    AND (topic_label IS NULL OR BTRIM(topic_label) <> '')
                ),
                ADD CONSTRAINT resources_source_kind_consistency CHECK (
                    (
                        kind = 'link'
                        AND source_url IS NOT NULL
                        AND source_url = BTRIM(source_url)
                        AND source_url ~ '^https://[^[:space:]]+$'
                    )
                    OR (kind = 'file' AND source_url IS NULL)
                ),
                ADD CONSTRAINT resources_version_positive CHECK (version >= 1),
                ADD CONSTRAINT resources_owner_foreign
                    FOREIGN KEY (user_id)
                    REFERENCES users (id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT resources_owner_course_foreign
                    FOREIGN KEY (user_id, course_id)
                    REFERENCES courses (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE stored_files
                ADD CONSTRAINT stored_files_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT stored_files_original_name_safe CHECK (
                    BTRIM(original_name) <> ''
                    AND original_name = BTRIM(original_name)
                    AND original_name NOT IN ('.', '..')
                    AND POSITION('/' IN original_name) = 0
                    AND POSITION(CHR(92) IN original_name) = 0
                    AND original_name !~ '[[:cntrl:]]'
                ),
                ADD CONSTRAINT stored_files_declared_mime_type_allowed CHECK (
                    declared_mime_type IN (
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'text/plain',
                        'text/markdown'
                    )
                ),
                ADD CONSTRAINT stored_files_expected_size_bounded CHECK (
                    expected_size BETWEEN 1 AND 26214400
                ),
                ADD CONSTRAINT stored_files_sha256_format CHECK (
                    sha256 ~ '^[0-9a-f]{64}$'
                ),
                ADD CONSTRAINT stored_files_upload_key_format CHECK (
                    upload_key IS NULL
                    OR upload_key ~ '^resources/v1/uploads/[0-9a-f]{32}$'
                ),
                ADD CONSTRAINT stored_files_object_key_format CHECK (
                    object_key ~ '^resources/v1/objects/[0-9a-f]{32}$'
                ),
                ADD CONSTRAINT stored_files_verified_mime_type_allowed CHECK (
                    verified_mime_type IS NULL
                    OR verified_mime_type IN (
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'text/plain',
                        'text/markdown'
                    )
                ),
                ADD CONSTRAINT stored_files_verified_size_bounded CHECK (
                    verified_size IS NULL
                    OR verified_size BETWEEN 1 AND 26214400
                ),
                ADD CONSTRAINT stored_files_status_known CHECK (
                    status IN ('pending', 'ready', 'deletion_pending')
                ),
                ADD CONSTRAINT stored_files_verification_consistency CHECK (
                    (
                        verified_mime_type IS NULL
                        AND verified_size IS NULL
                        AND ready_at IS NULL
                    )
                    OR (
                        verified_mime_type IS NOT NULL
                        AND verified_size = expected_size
                        AND ready_at IS NOT NULL
                    )
                ),
                ADD CONSTRAINT stored_files_lifecycle_consistency CHECK (
                    (
                        status = 'pending'
                        AND ready_at IS NULL
                        AND upload_key IS NOT NULL
                        AND cleanup_after IS NOT NULL
                    )
                    OR (
                        status = 'ready'
                        AND ready_at IS NOT NULL
                        AND (
                            (upload_key IS NOT NULL AND cleanup_after IS NOT NULL)
                            OR (
                                upload_key IS NULL
                                AND cleanup_after IS NULL
                                AND cleanup_started_at IS NULL
                            )
                        )
                    )
                    OR (
                        status = 'deletion_pending'
                        AND cleanup_after IS NOT NULL
                    )
                ),
                ADD CONSTRAINT stored_files_time_order CHECK (
                    upload_expires_at > created_at
                    AND (ready_at IS NULL OR ready_at >= created_at)
                    AND (cleanup_after IS NULL OR cleanup_after >= created_at)
                    AND (cleanup_started_at IS NULL OR cleanup_started_at >= created_at)
                    AND (
                        cleanup_started_at IS NULL
                        OR (
                            upload_key IS NOT NULL
                            AND cleanup_after >= cleanup_started_at
                        )
                    )
                    AND (
                        upload_key IS NULL
                        OR cleanup_after >= upload_expires_at
                    )
                ),
                ADD CONSTRAINT stored_files_cleanup_failures_bounded CHECK (
                    cleanup_failures BETWEEN 0 AND 16
                ),
                ADD CONSTRAINT stored_files_purge_readiness_consistency CHECK (
                    purge_ready_at IS NULL
                    OR (
                        status = 'deletion_pending'
                        AND purge_ready_at >= created_at
                    )
                ),
                ADD CONSTRAINT stored_files_owner_foreign
                    FOREIGN KEY (user_id)
                    REFERENCES users (id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT stored_files_owner_resource_foreign
                    FOREIGN KEY (user_id, resource_id)
                    REFERENCES resources (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX resources_owner_updated_cursor_idx
            ON resources (user_id, updated_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX resources_owner_kind_updated_cursor_idx
            ON resources (user_id, kind, updated_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX resources_owner_course_updated_cursor_idx
            ON resources (user_id, course_id, updated_at DESC, public_id DESC)
            WHERE course_id IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX resources_owner_topic_updated_cursor_idx
            ON resources (user_id, topic_label, updated_at DESC, public_id DESC)
            WHERE topic_label IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX resources_owner_title_prefix_idx
            ON resources (user_id, LOWER(title) text_pattern_ops)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX stored_files_cleanup_due_idx
            ON stored_files (cleanup_after, id)
            WHERE cleanup_after IS NOT NULL
            SQL);

        $this->createIntegrityTriggers();
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            if (Schema::hasTable('resources')) {
                DB::statement('LOCK TABLE resources IN ACCESS EXCLUSIVE MODE');
            }

            if (Schema::hasTable('stored_files')) {
                DB::statement('LOCK TABLE stored_files IN ACCESS EXCLUSIVE MODE');
            }

            $hasPrivateRows = (Schema::hasTable('stored_files') && DB::table('stored_files')->exists())
                || (Schema::hasTable('resources') && DB::table('resources')->exists());

            if ($hasPrivateRows) {
                throw new RuntimeException(
                    'Cannot roll back the resource storage foundation while private resource or file data exists.',
                );
            }

            Schema::dropIfExists('stored_files');
            Schema::dropIfExists('resources');

            DB::statement('DROP FUNCTION IF EXISTS educonnect_enforce_stored_file_resource_kind()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_guard_stored_file_delete()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_enforce_stored_file_transition()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_stored_file_key_update()');
            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_resource_storage_identity_update()');
        }, 3);
    }

    private function createIntegrityTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_resource_storage_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'resource_storage_public_id_immutable',
                        MESSAGE = TG_TABLE_NAME || '.public_id is immutable.';
                END IF;

                IF NEW.user_id IS DISTINCT FROM OLD.user_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'resource_storage_owner_immutable',
                        MESSAGE = TG_TABLE_NAME || '.user_id is immutable.';
                END IF;

                IF TG_TABLE_NAME = 'resources' THEN
                    IF NEW.kind IS DISTINCT FROM OLD.kind THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23514',
                            CONSTRAINT = 'resources_kind_immutable',
                            MESSAGE = 'resources.kind is immutable.';
                    END IF;
                END IF;

                IF TG_TABLE_NAME = 'stored_files' THEN
                    IF NEW.resource_id IS DISTINCT FROM OLD.resource_id THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23514',
                            CONSTRAINT = 'stored_files_resource_immutable',
                            MESSAGE = 'stored_files.resource_id is immutable.';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER resources_identity_immutable
            BEFORE UPDATE OF public_id, user_id, kind ON resources
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_resource_storage_identity_update();

            CREATE TRIGGER stored_files_identity_immutable
            BEFORE UPDATE OF public_id, user_id, resource_id ON stored_files
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_resource_storage_identity_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_stored_file_key_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.upload_key IS DISTINCT FROM OLD.upload_key
                    AND NOT (OLD.upload_key IS NOT NULL AND NEW.upload_key IS NULL) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'stored_files_upload_key_immutable',
                        MESSAGE = 'stored_files.upload_key is immutable.';
                END IF;

                IF NEW.object_key IS DISTINCT FROM OLD.object_key THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'stored_files_object_key_immutable',
                        MESSAGE = 'stored_files.object_key is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER stored_files_keys_immutable
            BEFORE UPDATE OF upload_key, object_key ON stored_files
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_stored_file_key_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_enforce_stored_file_resource_kind()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            DECLARE
                resource_kind TEXT;
            BEGIN
                SELECT kind
                INTO resource_kind
                FROM resources
                WHERE id = NEW.resource_id;

                IF NOT FOUND OR resource_kind <> 'file' THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'stored_files_resource_kind_file',
                        MESSAGE = 'stored_files must reference a file resource.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER stored_files_resource_kind_file
            BEFORE INSERT OR UPDATE OF user_id, resource_id ON stored_files
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_enforce_stored_file_resource_kind();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_enforce_stored_file_transition()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.original_name IS DISTINCT FROM OLD.original_name
                    OR NEW.declared_mime_type IS DISTINCT FROM OLD.declared_mime_type
                    OR NEW.expected_size IS DISTINCT FROM OLD.expected_size
                    OR NEW.sha256 IS DISTINCT FROM OLD.sha256 THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'stored_files_upload_contract_immutable',
                        MESSAGE = 'stored_files upload contract is immutable.';
                END IF;

                IF (OLD.status = 'deletion_pending' AND NEW.status <> 'deletion_pending')
                    OR (OLD.status = 'ready' AND NEW.status = 'pending') THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'stored_files_status_transition_valid',
                        MESSAGE = 'stored_files status transition is invalid.';
                END IF;

                IF (OLD.verified_mime_type IS NOT NULL
                        AND NEW.verified_mime_type IS DISTINCT FROM OLD.verified_mime_type)
                    OR (OLD.verified_size IS NOT NULL
                        AND NEW.verified_size IS DISTINCT FROM OLD.verified_size)
                    OR (OLD.ready_at IS NOT NULL
                        AND NEW.ready_at IS DISTINCT FROM OLD.ready_at) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'stored_files_verification_immutable',
                        MESSAGE = 'stored_files verified metadata is immutable.';
                END IF;

                IF NEW.purge_ready_at IS DISTINCT FROM OLD.purge_ready_at
                    AND NOT (
                        OLD.purge_ready_at IS NULL
                        AND NEW.purge_ready_at IS NOT NULL
                        AND OLD.status = 'deletion_pending'
                        AND NEW.status = 'deletion_pending'
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'stored_files_purge_readiness_immutable',
                        MESSAGE = 'stored_files purge readiness is write-once after deletion is pending.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER stored_files_transition_valid
            BEFORE UPDATE OF
                original_name,
                declared_mime_type,
                expected_size,
                sha256,
                status,
                verified_mime_type,
                verified_size,
                ready_at,
                purge_ready_at
            ON stored_files
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_enforce_stored_file_transition();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_guard_stored_file_delete()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF OLD.status <> 'deletion_pending' OR OLD.purge_ready_at IS NULL THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'stored_files_delete_requires_purge_readiness',
                        MESSAGE = 'stored_files require confirmed storage cleanup before purge.';
                END IF;

                RETURN OLD;
            END;
            $$;

            CREATE TRIGGER stored_files_delete_requires_purge_readiness
            BEFORE DELETE ON stored_files
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_guard_stored_file_delete();
            SQL);
    }
};
