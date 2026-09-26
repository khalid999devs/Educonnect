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
        Schema::create('academic_terms', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 80);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);

            $table->unique(['user_id', 'id'], 'academic_terms_owner_id_unique');
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('academic_term_id')->nullable();
            $table->string('title', 160);
            $table->string('code', 32)->nullable();
            $table->string('description', 2000)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedSmallInteger('onboarding_position')->nullable();
            $table->timestampTz('archived_at', 0)->nullable();
            $table->timestampsTz(0);
        });

        Schema::table('onboarding_progress', function (Blueprint $table): void {
            $table->timestampTz('academic_materialized_at', 0)->nullable();
        });

        DB::statement('ALTER TABLE academic_terms ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');
        DB::statement('ALTER TABLE courses ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');

        DB::statement(<<<'SQL'
            ALTER TABLE academic_terms
                ADD CONSTRAINT academic_terms_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT academic_terms_label_not_blank CHECK (BTRIM(label) <> ''),
                ADD CONSTRAINT academic_terms_date_order CHECK (
                    starts_on IS NULL OR ends_on IS NULL OR starts_on <= ends_on
                ),
                ADD CONSTRAINT academic_terms_version_positive CHECK (version >= 1)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE courses
                ADD CONSTRAINT courses_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT courses_text_not_blank CHECK (
                    BTRIM(title) <> ''
                    AND (code IS NULL OR BTRIM(code) <> '')
                    AND (description IS NULL OR BTRIM(description) <> '')
                ),
                ADD CONSTRAINT courses_version_positive CHECK (version >= 1),
                ADD CONSTRAINT courses_onboarding_position CHECK (
                    onboarding_position IS NULL OR onboarding_position BETWEEN 0 AND 11
                ),
                ADD CONSTRAINT courses_owner_term_foreign
                    FOREIGN KEY (user_id, academic_term_id)
                    REFERENCES academic_terms (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_progress
                ADD CONSTRAINT onboarding_progress_academic_materialized_after_completion CHECK (
                    academic_materialized_at IS NULL OR completed_at IS NOT NULL
                )
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX courses_owner_onboarding_position_unique
            ON courses (user_id, onboarding_position)
            WHERE onboarding_position IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX academic_terms_owner_updated_cursor_idx
            ON academic_terms (user_id, updated_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX academic_terms_owner_created_cursor_idx
            ON academic_terms (user_id, created_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX academic_terms_owner_label_prefix_idx
            ON academic_terms (user_id, LOWER(label) text_pattern_ops)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX courses_owner_updated_cursor_idx
            ON courses (user_id, updated_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX courses_owner_created_cursor_idx
            ON courses (user_id, created_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX courses_owner_active_updated_cursor_idx
            ON courses (user_id, updated_at DESC, public_id DESC)
            WHERE archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX courses_owner_active_created_cursor_idx
            ON courses (user_id, created_at DESC, public_id DESC)
            WHERE archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX courses_owner_term_active_updated_cursor_idx
            ON courses (user_id, academic_term_id, updated_at DESC, public_id DESC)
            WHERE archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX courses_owner_term_active_created_cursor_idx
            ON courses (user_id, academic_term_id, created_at DESC, public_id DESC)
            WHERE archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX courses_term_archive_lookup_idx
            ON courses (academic_term_id, archived_at)
            WHERE academic_term_id IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX courses_owner_title_prefix_idx
            ON courses (user_id, LOWER(title) text_pattern_ops)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX courses_owner_code_prefix_idx
            ON courses (user_id, LOWER(code) text_pattern_ops)
            WHERE code IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX onboarding_progress_academic_materialization_backlog_idx
            ON onboarding_progress (user_id)
            WHERE completed_at IS NOT NULL AND academic_materialized_at IS NULL
            SQL);

        $this->createImmutabilityTriggers();
    }

    public function down(): void
    {
        $hasAcademicRows = (Schema::hasTable('courses') && DB::table('courses')->exists())
            || (Schema::hasTable('academic_terms') && DB::table('academic_terms')->exists());
        $hasMaterializationMarker = Schema::hasColumn('onboarding_progress', 'academic_materialized_at')
            && DB::table('onboarding_progress')->whereNotNull('academic_materialized_at')->exists();

        if ($hasAcademicRows || $hasMaterializationMarker) {
            throw new RuntimeException(
                'Cannot roll back the academic foundation while course, term, or materialization data exists.',
            );
        }

        Schema::dropIfExists('courses');
        Schema::dropIfExists('academic_terms');

        if (Schema::hasColumn('onboarding_progress', 'academic_materialized_at')) {
            DB::statement(
                'ALTER TABLE onboarding_progress '
                .'DROP CONSTRAINT IF EXISTS onboarding_progress_academic_materialized_after_completion',
            );
            DB::statement('DROP INDEX IF EXISTS onboarding_progress_academic_materialization_backlog_idx');

            Schema::table('onboarding_progress', function (Blueprint $table): void {
                $table->dropColumn('academic_materialized_at');
            });
        }

        DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_academic_identity_update()');
        DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_course_provenance_update()');
    }

    private function createImmutabilityTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_academic_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'academic_public_id_immutable',
                        MESSAGE = TG_TABLE_NAME || '.public_id is immutable.';
                END IF;

                IF NEW.user_id IS DISTINCT FROM OLD.user_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'academic_owner_immutable',
                        MESSAGE = TG_TABLE_NAME || '.user_id is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER academic_terms_identity_immutable
            BEFORE UPDATE OF public_id, user_id ON academic_terms
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_academic_identity_update();

            CREATE TRIGGER courses_identity_immutable
            BEFORE UPDATE OF public_id, user_id ON courses
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_academic_identity_update();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_course_provenance_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.onboarding_position IS DISTINCT FROM OLD.onboarding_position THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'courses_onboarding_provenance_immutable',
                        MESSAGE = 'courses.onboarding_position is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER courses_onboarding_provenance_immutable
            BEFORE UPDATE OF onboarding_position ON courses
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_course_provenance_update();
            SQL);
    }
};
