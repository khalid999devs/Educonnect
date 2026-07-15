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
        Schema::table('courses', function (Blueprint $table): void {
            $table->unique(['user_id', 'id'], 'courses_owner_id_unique');
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('title', 160);
            $table->string('description', 2000)->nullable();
            $table->timestampTz('due_at', 0)->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestampTz('completed_at', 0)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('archived_at', 0)->nullable();
            $table->timestampsTz(0);

            $table->unique(['user_id', 'id'], 'tasks_owner_id_unique');
        });

        Schema::create('focus_sessions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->timestampTz('starts_at', 0);
            $table->timestampTz('ends_at', 0);
            $table->string('note', 2000)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(0);
        });

        DB::statement('ALTER TABLE tasks ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');
        DB::statement('ALTER TABLE focus_sessions ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');

        DB::statement(<<<'SQL'
            ALTER TABLE tasks
                ADD CONSTRAINT tasks_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT tasks_text_not_blank CHECK (
                    BTRIM(title) <> ''
                    AND (description IS NULL OR BTRIM(description) <> '')
                ),
                ADD CONSTRAINT tasks_status_known CHECK (
                    status IN ('pending', 'in_progress', 'completed')
                ),
                ADD CONSTRAINT tasks_completion_consistency CHECK (
                    (status = 'completed') = (completed_at IS NOT NULL)
                ),
                ADD CONSTRAINT tasks_version_positive CHECK (version >= 1),
                ADD CONSTRAINT tasks_owner_course_foreign
                    FOREIGN KEY (user_id, course_id)
                    REFERENCES courses (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE focus_sessions
                ADD CONSTRAINT focus_sessions_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT focus_sessions_target_exclusive CHECK (
                    task_id IS NULL OR course_id IS NULL
                ),
                ADD CONSTRAINT focus_sessions_time_range CHECK (
                    starts_at < ends_at
                    AND ends_at <= starts_at + INTERVAL '24 hours'
                ),
                ADD CONSTRAINT focus_sessions_note_not_blank CHECK (
                    note IS NULL OR BTRIM(note) <> ''
                ),
                ADD CONSTRAINT focus_sessions_version_positive CHECK (version >= 1),
                ADD CONSTRAINT focus_sessions_owner_task_foreign
                    FOREIGN KEY (user_id, task_id)
                    REFERENCES tasks (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT focus_sessions_owner_course_foreign
                    FOREIGN KEY (user_id, course_id)
                    REFERENCES courses (user_id, id)
                    ON UPDATE RESTRICT
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX tasks_owner_updated_cursor_idx
            ON tasks (user_id, updated_at DESC, public_id DESC)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX tasks_owner_active_updated_cursor_idx
            ON tasks (user_id, updated_at DESC, public_id DESC)
            WHERE archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX tasks_owner_active_due_cursor_idx
            ON tasks (user_id, due_at, public_id)
            WHERE archived_at IS NULL AND due_at IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX tasks_owner_active_status_due_idx
            ON tasks (user_id, status, due_at, public_id)
            WHERE archived_at IS NULL AND due_at IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX tasks_owner_course_lookup_idx
            ON tasks (user_id, course_id)
            WHERE course_id IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX tasks_owner_title_prefix_idx
            ON tasks (user_id, LOWER(title) text_pattern_ops)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX focus_sessions_owner_starts_cursor_idx
            ON focus_sessions (user_id, starts_at, public_id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX focus_sessions_owner_time_range_idx
            ON focus_sessions (user_id, starts_at, ends_at)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX focus_sessions_owner_task_lookup_idx
            ON focus_sessions (user_id, task_id, starts_at, public_id)
            WHERE task_id IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX focus_sessions_owner_course_lookup_idx
            ON focus_sessions (user_id, course_id, starts_at, public_id)
            WHERE course_id IS NOT NULL
            SQL);

        $this->createIdentityImmutabilityTriggers();
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            if (Schema::hasTable('tasks')) {
                DB::statement('LOCK TABLE tasks IN ACCESS EXCLUSIVE MODE');
            }

            if (Schema::hasTable('focus_sessions')) {
                DB::statement('LOCK TABLE focus_sessions IN ACCESS EXCLUSIVE MODE');
            }

            $hasPlannerRows = (Schema::hasTable('focus_sessions') && DB::table('focus_sessions')->exists())
                || (Schema::hasTable('tasks') && DB::table('tasks')->exists());

            if ($hasPlannerRows) {
                throw new RuntimeException(
                    'Cannot roll back the planner foundation while task or focus-session data exists.',
                );
            }

            Schema::dropIfExists('focus_sessions');
            Schema::dropIfExists('tasks');

            if (Schema::hasTable('courses')) {
                DB::statement('ALTER TABLE courses DROP CONSTRAINT IF EXISTS courses_owner_id_unique');
            }

            DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_planner_identity_update()');
        }, 3);
    }

    private function createIdentityImmutabilityTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_planner_identity_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'planner_public_id_immutable',
                        MESSAGE = TG_TABLE_NAME || '.public_id is immutable.';
                END IF;

                IF NEW.user_id IS DISTINCT FROM OLD.user_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'planner_owner_immutable',
                        MESSAGE = TG_TABLE_NAME || '.user_id is immutable.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER tasks_identity_immutable
            BEFORE UPDATE OF public_id, user_id ON tasks
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_planner_identity_update();

            CREATE TRIGGER focus_sessions_identity_immutable
            BEFORE UPDATE OF public_id, user_id ON focus_sessions
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_planner_identity_update();
            SQL);
    }
};
