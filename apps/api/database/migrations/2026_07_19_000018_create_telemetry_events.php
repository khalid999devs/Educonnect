<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The operational metrics substrate (Phase 28, observability increment).
 *
 * Unlike `audit_events`, telemetry is diagnostic, not compliance evidence: it
 * carries no actor, no reason, and no private academic content - only bounded,
 * redacted operational facts (provider/job/error names, outcomes, latency). It
 * is prunable by retention, so it is a plain append-and-prune table (no
 * immutability trigger, no ULID) with CHECK-constrained enum columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_events', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->string('name', 120);
            $table->string('outcome', 16);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampTz('occurred_at', 3)->useCurrent();

            $table->index(['type', 'occurred_at']);
            $table->index(['type', 'outcome', 'occurred_at']);
            $table->index('occurred_at');
        });

        DB::statement("ALTER TABLE telemetry_events ADD CONSTRAINT telemetry_events_type_allowed CHECK (type IN ('ai_call', 'intake_job', 'error', 'http_request'))");
        DB::statement("ALTER TABLE telemetry_events ADD CONSTRAINT telemetry_events_outcome_allowed CHECK (outcome IN ('success', 'failure', 'fallback', 'degraded'))");
        DB::statement("ALTER TABLE telemetry_events ADD CONSTRAINT telemetry_events_name_format CHECK (name ~ '^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$')");
        DB::statement('ALTER TABLE telemetry_events ADD CONSTRAINT telemetry_events_status_range CHECK (status_code IS NULL OR (status_code BETWEEN 100 AND 599))');
        DB::statement("ALTER TABLE telemetry_events ADD CONSTRAINT telemetry_events_metadata_object CHECK (JSONB_TYPEOF(metadata) = 'object')");
        DB::statement('ALTER TABLE telemetry_events ADD CONSTRAINT telemetry_events_metadata_size CHECK (OCTET_LENGTH(metadata::text) <= 8192)');
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_events');
    }
};
