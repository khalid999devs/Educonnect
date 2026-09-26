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
        Schema::create('intake_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('intake_item_id');
            $table->string('kind', 16);
            $table->jsonb('payload');
            $table->string('schema_version', 16)->default('v1');
            $table->decimal('confidence', 4, 3);
            $table->string('reason', 1000);
            $table->string('status', 16)->default('proposed');
            $table->unsignedBigInteger('created_task_id')->nullable();
            $table->unsignedBigInteger('created_resource_id')->nullable();
            $table->timestampsTz(0);
        });

        DB::statement('ALTER TABLE intake_suggestions ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');

        DB::statement('ALTER TABLE intake_items ADD COLUMN classification_provider VARCHAR(64) NULL');
        DB::statement('ALTER TABLE intake_items ADD COLUMN classification_model VARCHAR(128) NULL');
        DB::statement('ALTER TABLE intake_items ADD COLUMN classification_schema_version VARCHAR(16) NULL');
        DB::statement('ALTER TABLE intake_items ADD COLUMN classification_latency_ms INTEGER NULL');

        $this->addConstraints();
        $this->createIndexes();
        $this->createIntegrityTriggers();
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            if (Schema::hasTable('intake_suggestions')) {
                DB::statement('LOCK TABLE intake_suggestions IN ACCESS EXCLUSIVE MODE');

                if (DB::table('intake_suggestions')->exists()) {
                    throw new RuntimeException(
                        'Cannot roll back intake suggestions while private suggestion data exists.',
                    );
                }
            }

            if (Schema::hasTable('intake_items')
                && Schema::hasColumn('intake_items', 'classification_provider')
                && DB::table('intake_items')->whereNotNull('classification_provider')->exists()) {
                throw new RuntimeException(
                    'Cannot roll back intake suggestions while private suggestion data exists.',
                );
            }

            Schema::dropIfExists('intake_suggestions');

            if (Schema::hasTable('intake_items')) {
                DB::statement('ALTER TABLE intake_items DROP COLUMN IF EXISTS classification_latency_ms');
                DB::statement('ALTER TABLE intake_items DROP COLUMN IF EXISTS classification_schema_version');
                DB::statement('ALTER TABLE intake_items DROP COLUMN IF EXISTS classification_model');
                DB::statement('ALTER TABLE intake_items DROP COLUMN IF EXISTS classification_provider');
            }

            DB::statement('DROP FUNCTION IF EXISTS educonnect_enforce_intake_suggestion_transition()');
        }, 3);
    }

    private function addConstraints(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE intake_suggestions
                ADD CONSTRAINT intake_suggestions_public_id_format CHECK (
                    public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$'
                ),
                ADD CONSTRAINT intake_suggestions_item_foreign
                    FOREIGN KEY (intake_item_id)
                    REFERENCES intake_items (id)
                    ON UPDATE RESTRICT
                    ON DELETE CASCADE
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT intake_suggestions_task_foreign
                    FOREIGN KEY (created_task_id)
                    REFERENCES tasks (id)
                    ON UPDATE RESTRICT
                    ON DELETE SET NULL
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT intake_suggestions_resource_foreign
                    FOREIGN KEY (created_resource_id)
                    REFERENCES resources (id)
                    ON UPDATE RESTRICT
                    ON DELETE SET NULL
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT intake_suggestions_kind_known CHECK (
                    kind IN ('task', 'resource')
                ),
                ADD CONSTRAINT intake_suggestions_payload_is_object CHECK (
                    JSONB_TYPEOF(payload) = 'object'
                ),
                ADD CONSTRAINT intake_suggestions_schema_version_valid CHECK (
                    schema_version = BTRIM(schema_version)
                    AND CHAR_LENGTH(schema_version) BETWEEN 1 AND 16
                ),
                ADD CONSTRAINT intake_suggestions_confidence_bounded CHECK (
                    confidence >= 0 AND confidence <= 1
                ),
                ADD CONSTRAINT intake_suggestions_reason_valid CHECK (
                    reason = BTRIM(reason)
                    AND CHAR_LENGTH(reason) BETWEEN 1 AND 1000
                ),
                ADD CONSTRAINT intake_suggestions_status_known CHECK (
                    status IN ('proposed', 'dismissed', 'applied')
                ),
                ADD CONSTRAINT intake_suggestions_created_targets_consistent CHECK (
                    status = 'applied'
                    OR (created_task_id IS NULL AND created_resource_id IS NULL)
                ),
                ADD CONSTRAINT intake_suggestions_time_order CHECK (
                    updated_at >= created_at
                )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE intake_items
                ADD CONSTRAINT intake_items_classification_metadata_valid CHECK (
                    (
                        classification_provider IS NULL
                        OR (
                            classification_provider = BTRIM(classification_provider)
                            AND CHAR_LENGTH(classification_provider) BETWEEN 1 AND 64
                        )
                    )
                    AND (
                        classification_model IS NULL
                        OR (
                            classification_model = BTRIM(classification_model)
                            AND CHAR_LENGTH(classification_model) BETWEEN 1 AND 128
                        )
                    )
                    AND (
                        classification_schema_version IS NULL
                        OR (
                            classification_schema_version = BTRIM(classification_schema_version)
                            AND CHAR_LENGTH(classification_schema_version) BETWEEN 1 AND 16
                        )
                    )
                    AND (classification_latency_ms IS NULL OR classification_latency_ms >= 0)
                )
            SQL);
    }

    private function createIndexes(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX intake_suggestions_item_status_idx
            ON intake_suggestions (intake_item_id, status, id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX intake_suggestions_task_lookup_idx
            ON intake_suggestions (created_task_id)
            WHERE created_task_id IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX intake_suggestions_resource_lookup_idx
            ON intake_suggestions (created_resource_id)
            WHERE created_resource_id IS NOT NULL
            SQL);
    }

    private function createIntegrityTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_enforce_intake_suggestion_transition()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.public_id IS DISTINCT FROM OLD.public_id
                    OR NEW.intake_item_id IS DISTINCT FROM OLD.intake_item_id
                    OR NEW.kind IS DISTINCT FROM OLD.kind
                    OR NEW.payload IS DISTINCT FROM OLD.payload
                    OR NEW.schema_version IS DISTINCT FROM OLD.schema_version
                    OR NEW.confidence IS DISTINCT FROM OLD.confidence
                    OR NEW.reason IS DISTINCT FROM OLD.reason THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'intake_suggestions_identity_immutable',
                        MESSAGE = 'Intake suggestion identity, payload, and reasoning are immutable.';
                END IF;

                IF NEW.status IS DISTINCT FROM OLD.status
                    AND NOT (
                        (OLD.status = 'proposed' AND NEW.status IN ('dismissed', 'applied'))
                        OR (OLD.status = 'dismissed' AND NEW.status = 'proposed')
                    ) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        CONSTRAINT = 'intake_suggestions_status_transition_valid',
                        MESSAGE = 'intake_suggestions.status transition is invalid.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER intake_suggestions_transition_valid
            BEFORE UPDATE ON intake_suggestions
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_enforce_intake_suggestion_transition();
            SQL);
    }
};
