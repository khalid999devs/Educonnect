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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 16)->default('active')->after('password');
            $table->timestampTz('suspended_at', 0)->nullable()->after('status');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_valid CHECK (status IN ('active', 'suspended'))");
        // Suspension timestamp and status are kept consistent: a suspended account
        // always carries the instant it was suspended; an active one never does.
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_suspended_at_consistent CHECK ((status = 'suspended' AND suspended_at IS NOT NULL) OR (status = 'active' AND suspended_at IS NULL))");

        // Admin user directory paginates newest-first with a public_id tiebreaker.
        DB::statement('CREATE INDEX users_admin_created_cursor_idx ON users (created_at DESC, public_id DESC)');
        // Suspended accounts are rare; a partial index keeps the status filter cheap.
        DB::statement("CREATE INDEX users_suspended_idx ON users (suspended_at DESC) WHERE status = 'suspended'");

        // The append-only audit trail is read newest-first in the admin viewer.
        DB::statement('CREATE INDEX audit_events_created_cursor_idx ON audit_events (created_at DESC, public_id DESC)');
    }

    public function down(): void
    {
        if (DB::table('users')->where('status', 'suspended')->exists()) {
            throw new RuntimeException('Cannot roll back account status while suspended accounts exist.');
        }

        DB::statement('DROP INDEX IF EXISTS audit_events_created_cursor_idx');
        DB::statement('DROP INDEX IF EXISTS users_suspended_idx');
        DB::statement('DROP INDEX IF EXISTS users_admin_created_cursor_idx');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_suspended_at_consistent');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_valid');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['status', 'suspended_at']);
        });
    }
};
