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
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('institution_name', 200)->nullable();
            $table->char('institution_country_code', 2)->nullable();
            $table->string('department', 160)->nullable();
            $table->string('degree', 160)->nullable();
            $table->string('major', 160)->nullable();
            $table->string('year_label', 80)->nullable();
            $table->string('term_label', 80)->nullable();
            $table->timestampsTz(0);
        });

        Schema::create('onboarding_progress', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(0);
            $table->string('institution_state', 16)->default('pending');
            $table->string('program_state', 16)->default('pending');
            $table->string('study_stage_state', 16)->default('pending');
            $table->string('courses_state', 16)->default('pending');
            $table->string('goals_state', 16)->default('pending');
            $table->string('first_source_state', 16)->default('pending');
            $table->string('first_source_url', 2048)->nullable();
            $table->string('first_source_title', 255)->nullable();
            $table->timestampTz('completed_at', 0)->nullable();
            $table->timestampsTz(0);
        });

        Schema::create('onboarding_course_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title', 160);
            $table->string('code', 32)->nullable();
            $table->timestampsTz(0);
            $table->unique(['user_id', 'position']);
        });

        Schema::create('onboarding_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->unsignedSmallInteger('position');
            $table->string('text', 200);
            $table->timestampsTz(0);
            $table->unique(['user_id', 'kind', 'position']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE user_profiles
                ADD CONSTRAINT user_profiles_institution_pair CHECK (
                    (institution_name IS NULL) = (institution_country_code IS NULL)
                ),
                ADD CONSTRAINT user_profiles_country_format CHECK (
                    institution_country_code IS NULL OR institution_country_code ~ '^[A-Z]{2}$'
                ),
                ADD CONSTRAINT user_profiles_text_not_blank CHECK (
                    (institution_name IS NULL OR BTRIM(institution_name) <> '')
                    AND (department IS NULL OR BTRIM(department) <> '')
                    AND (degree IS NULL OR BTRIM(degree) <> '')
                    AND (major IS NULL OR BTRIM(major) <> '')
                    AND (year_label IS NULL OR BTRIM(year_label) <> '')
                    AND (term_label IS NULL OR BTRIM(term_label) <> '')
                )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_progress
                ADD CONSTRAINT onboarding_progress_version_nonnegative CHECK (version >= 0),
                ADD CONSTRAINT onboarding_progress_states CHECK (
                    institution_state IN ('pending', 'completed')
                    AND program_state IN ('pending', 'completed', 'skipped')
                    AND study_stage_state IN ('pending', 'completed', 'skipped')
                    AND courses_state IN ('pending', 'completed', 'skipped')
                    AND goals_state IN ('pending', 'completed', 'skipped')
                    AND first_source_state IN ('pending', 'completed', 'skipped')
                ),
                ADD CONSTRAINT onboarding_progress_source_consistency CHECK (
                    (first_source_state = 'completed' AND first_source_url IS NOT NULL)
                    OR (first_source_state <> 'completed' AND first_source_url IS NULL AND first_source_title IS NULL)
                ),
                ADD CONSTRAINT onboarding_progress_source_not_blank CHECK (
                    (first_source_url IS NULL OR BTRIM(first_source_url) <> '')
                    AND (first_source_title IS NULL OR BTRIM(first_source_title) <> '')
                ),
                ADD CONSTRAINT onboarding_progress_completion_states CHECK (
                    completed_at IS NULL OR (
                        institution_state = 'completed'
                        AND program_state <> 'pending'
                        AND study_stage_state <> 'pending'
                        AND courses_state <> 'pending'
                        AND goals_state <> 'pending'
                        AND first_source_state <> 'pending'
                    )
                )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_course_drafts
                ADD CONSTRAINT onboarding_course_drafts_position CHECK (position BETWEEN 0 AND 11),
                ADD CONSTRAINT onboarding_course_drafts_text_not_blank CHECK (
                    BTRIM(title) <> '' AND (code IS NULL OR BTRIM(code) <> '')
                )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_intents
                ADD CONSTRAINT onboarding_intents_kind CHECK (kind IN ('goal', 'problem')),
                ADD CONSTRAINT onboarding_intents_position CHECK (position BETWEEN 0 AND 7),
                ADD CONSTRAINT onboarding_intents_text_not_blank CHECK (BTRIM(text) <> '')
            SQL);

        $this->createAggregateConsistencyTriggers();
    }

    public function down(): void
    {
        foreach (['onboarding_course_drafts', 'onboarding_intents', 'onboarding_progress', 'user_profiles'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Cannot roll back the onboarding foundation while private profile data exists.');
            }
        }

        Schema::dropIfExists('onboarding_intents');
        Schema::dropIfExists('onboarding_course_drafts');
        Schema::dropIfExists('onboarding_progress');
        Schema::dropIfExists('user_profiles');
        DB::statement('DROP FUNCTION IF EXISTS enforce_onboarding_aggregate_consistency()');
    }

    private function createAggregateConsistencyTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_onboarding_aggregate_consistency()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                target_user_id bigint;
                progress onboarding_progress%ROWTYPE;
                profile user_profiles%ROWTYPE;
                course_count integer;
                goal_count integer;
                problem_count integer;
            BEGIN
                IF TG_OP = 'UPDATE' AND NEW.user_id IS DISTINCT FROM OLD.user_id THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'onboarding_aggregate_consistency',
                        MESSAGE = 'Onboarding aggregate ownership is immutable.';
                END IF;

                IF TG_OP = 'DELETE' THEN
                    target_user_id := OLD.user_id;
                ELSE
                    target_user_id := NEW.user_id;
                END IF;

                SELECT * INTO progress
                FROM onboarding_progress
                WHERE user_id = target_user_id
                FOR UPDATE;

                IF NOT FOUND THEN
                    IF TG_OP <> 'DELETE' THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23514',
                            CONSTRAINT = 'onboarding_aggregate_consistency',
                            MESSAGE = 'Onboarding child data requires a progress aggregate.';
                    END IF;

                    IF TG_TABLE_NAME = 'onboarding_progress'
                        AND EXISTS (SELECT 1 FROM users WHERE id = target_user_id)
                        AND (
                            EXISTS (SELECT 1 FROM user_profiles WHERE user_id = target_user_id)
                            OR EXISTS (SELECT 1 FROM onboarding_course_drafts WHERE user_id = target_user_id)
                            OR EXISTS (SELECT 1 FROM onboarding_intents WHERE user_id = target_user_id)
                        ) THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23514',
                            CONSTRAINT = 'onboarding_aggregate_consistency',
                            MESSAGE = 'Onboarding progress cannot be removed while child data exists.';
                    END IF;

                    RETURN NULL;
                END IF;

                SELECT * INTO profile
                FROM user_profiles
                WHERE user_id = target_user_id;

                SELECT COUNT(*) INTO course_count
                FROM onboarding_course_drafts
                WHERE user_id = target_user_id;

                SELECT COUNT(*) FILTER (WHERE kind = 'goal'),
                       COUNT(*) FILTER (WHERE kind = 'problem')
                INTO goal_count, problem_count
                FROM onboarding_intents
                WHERE user_id = target_user_id;

                IF progress.institution_state = 'completed' THEN
                    IF profile.user_id IS NULL
                        OR profile.institution_name IS NULL
                        OR profile.institution_country_code IS NULL THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23514',
                            CONSTRAINT = 'onboarding_aggregate_consistency',
                            MESSAGE = 'A completed institution step requires institution profile data.';
                    END IF;
                ELSIF profile.institution_name IS NOT NULL OR profile.institution_country_code IS NOT NULL THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'onboarding_aggregate_consistency',
                        MESSAGE = 'Institution profile data requires a completed institution step.';
                END IF;

                IF progress.program_state = 'completed' THEN
                    IF profile.user_id IS NULL
                        OR (profile.department IS NULL AND profile.degree IS NULL AND profile.major IS NULL) THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23514',
                            CONSTRAINT = 'onboarding_aggregate_consistency',
                            MESSAGE = 'A completed program step requires program profile data.';
                    END IF;
                ELSIF profile.department IS NOT NULL OR profile.degree IS NOT NULL OR profile.major IS NOT NULL THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'onboarding_aggregate_consistency',
                        MESSAGE = 'Program profile data requires a completed program step.';
                END IF;

                IF progress.study_stage_state = 'completed' THEN
                    IF profile.user_id IS NULL
                        OR (profile.year_label IS NULL AND profile.term_label IS NULL) THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23514',
                            CONSTRAINT = 'onboarding_aggregate_consistency',
                            MESSAGE = 'A completed study-stage step requires study-stage profile data.';
                    END IF;
                ELSIF profile.year_label IS NOT NULL OR profile.term_label IS NOT NULL THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'onboarding_aggregate_consistency',
                        MESSAGE = 'Study-stage profile data requires a completed study-stage step.';
                END IF;

                IF (progress.courses_state = 'completed') <> (course_count > 0) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'onboarding_aggregate_consistency',
                        MESSAGE = 'Course drafts must match the course-step state.';
                END IF;

                IF (progress.goals_state = 'completed') <> (goal_count + problem_count > 0) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'onboarding_aggregate_consistency',
                        MESSAGE = 'Goals and problems must match the goals-step state.';
                END IF;

                IF progress.completed_at IS NOT NULL
                    AND course_count = 0
                    AND goal_count = 0
                    AND problem_count = 0 THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'onboarding_aggregate_consistency',
                        MESSAGE = 'Completed onboarding requires a starter-workspace seed.';
                END IF;

                RETURN NULL;
            END;
            $function$
            SQL);

        foreach ([
            'user_profiles' => 'user_profiles_onboarding_consistency',
            'onboarding_progress' => 'onboarding_progress_aggregate_consistency',
            'onboarding_course_drafts' => 'onboarding_course_drafts_aggregate_consistency',
            'onboarding_intents' => 'onboarding_intents_aggregate_consistency',
        ] as $table => $trigger) {
            DB::statement(<<<SQL
                CREATE CONSTRAINT TRIGGER {$trigger}
                AFTER INSERT OR UPDATE OR DELETE ON {$table}
                DEFERRABLE INITIALLY IMMEDIATE
                FOR EACH ROW
                EXECUTE FUNCTION enforce_onboarding_aggregate_consistency()
                SQL);
        }
    }
};
