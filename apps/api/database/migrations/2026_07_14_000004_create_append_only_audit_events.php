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
        $this->createAuditUlidGenerator();

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('actor_type', 16);
            // Deliberately not a foreign key: deleting a user must not mutate or block immutable evidence.
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->ulid('actor_public_id')->nullable();
            $table->string('action', 100);
            $table->string('subject_type', 100);
            $table->string('subject_id', 128);
            $table->text('reason');
            $table->string('request_id', 36);
            $table->jsonb('before_state');
            $table->jsonb('after_state');
            $table->timestampTz('created_at', 0)->useCurrent();

            $table->index(['actor_user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index('request_id');
        });

        DB::statement('ALTER TABLE audit_events ALTER COLUMN public_id SET DEFAULT educonnect_generate_audit_ulid()');
        DB::statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_public_id_format CHECK (public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$')");
        DB::statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_actor_public_id_format CHECK (actor_public_id IS NULL OR actor_public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$')");
        DB::statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_actor CHECK ((actor_type = 'system' AND actor_user_id IS NULL AND actor_public_id IS NULL) OR (actor_type = 'user' AND actor_public_id IS NOT NULL))");
        DB::statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_action_format CHECK (action ~ '^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_subject_type_format CHECK (subject_type ~ '^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$')");
        DB::statement('ALTER TABLE audit_events ADD CONSTRAINT audit_events_reason_present CHECK (LENGTH(BTRIM(reason)) BETWEEN 1 AND 2000)');
        DB::statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_request_id_format CHECK (request_id ~ '^req_[a-f0-9]{32}$')");
        DB::statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_state_objects CHECK (JSONB_TYPEOF(before_state) = 'object' AND JSONB_TYPEOF(after_state) = 'object')");
        DB::statement('ALTER TABLE audit_events ADD CONSTRAINT audit_events_state_size CHECK (OCTET_LENGTH(before_state::text) <= 16384 AND OCTET_LENGTH(after_state::text) <= 16384)');
        $this->createAuditImmutabilityTrigger();
    }

    public function down(): void
    {
        if (DB::table('audit_events')->exists()) {
            throw new RuntimeException('Cannot roll back immutable audit events without an explicit evidence-retention plan.');
        }

        DB::statement('DROP TRIGGER IF EXISTS audit_events_append_only ON audit_events');
        DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_audit_event_mutation()');
        Schema::dropIfExists('audit_events');
        DB::statement('DROP FUNCTION IF EXISTS educonnect_generate_audit_ulid()');
    }

    private function createAuditUlidGenerator(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_generate_audit_ulid()
            RETURNS CHARACTER(26)
            LANGUAGE plpgsql
            VOLATILE
            AS $$
            DECLARE
                source BYTEA := uuid_send(uuidv7());
                alphabet CONSTANT TEXT := '0123456789abcdefghjkmnpqrstvwxyz';
                result TEXT := '';
                digit_position INTEGER;
                bit_position INTEGER;
                source_position INTEGER;
                digit_value INTEGER;
            BEGIN
                FOR digit_position IN 0..25 LOOP
                    digit_value := 0;

                    FOR bit_position IN 0..4 LOOP
                        source_position := (digit_position * 5 + bit_position) - 2;
                        digit_value := digit_value * 2;

                        IF source_position >= 0 THEN
                            digit_value := digit_value + get_bit(
                                source,
                                (source_position / 8) * 8 + (7 - (source_position % 8))
                            );
                        END IF;
                    END LOOP;

                    result := result || substr(alphabet, digit_value + 1, 1);
                END LOOP;

                RETURN result::CHARACTER(26);
            END;
            $$;
            SQL);
    }

    private function createAuditImmutabilityTrigger(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_audit_event_mutation()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'audit_events are append-only' USING ERRCODE = '23514';
            END;
            $$;

            CREATE TRIGGER audit_events_append_only
            BEFORE UPDATE OR DELETE ON audit_events
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_audit_event_mutation();
            SQL);
    }
};
