<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertLegacyTimestampProvenance();

        DB::statement("ALTER TABLE users ALTER COLUMN email_verified_at TYPE TIMESTAMP(0) WITH TIME ZONE USING email_verified_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE users ALTER COLUMN created_at TYPE TIMESTAMP(0) WITH TIME ZONE USING created_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE users ALTER COLUMN updated_at TYPE TIMESTAMP(0) WITH TIME ZONE USING updated_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE password_reset_tokens ALTER COLUMN created_at TYPE TIMESTAMP(0) WITH TIME ZONE USING created_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE personal_access_tokens ALTER COLUMN last_used_at TYPE TIMESTAMP(0) WITH TIME ZONE USING last_used_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE personal_access_tokens ALTER COLUMN expires_at TYPE TIMESTAMP(0) WITH TIME ZONE USING expires_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE personal_access_tokens ALTER COLUMN created_at TYPE TIMESTAMP(0) WITH TIME ZONE USING created_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE personal_access_tokens ALTER COLUMN updated_at TYPE TIMESTAMP(0) WITH TIME ZONE USING updated_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE failed_jobs ALTER COLUMN failed_at TYPE TIMESTAMP(0) WITH TIME ZONE USING failed_at AT TIME ZONE 'UTC'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE failed_jobs ALTER COLUMN failed_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING failed_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE personal_access_tokens ALTER COLUMN updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING updated_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE personal_access_tokens ALTER COLUMN created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING created_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE personal_access_tokens ALTER COLUMN expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING expires_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE personal_access_tokens ALTER COLUMN last_used_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING last_used_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE password_reset_tokens ALTER COLUMN created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING created_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE users ALTER COLUMN updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING updated_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE users ALTER COLUMN created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING created_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE users ALTER COLUMN email_verified_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING email_verified_at AT TIME ZONE 'UTC'");
    }

    private function assertLegacyTimestampProvenance(): void
    {
        if (! $this->hasLegacyTimestampValues()) {
            return;
        }

        if (config('database.legacy_timestamp_timezone') !== 'UTC') {
            throw new RuntimeException(
                'Cannot convert legacy timestamps: set DB_LEGACY_TIMESTAMP_TIMEZONE=UTC only after verifying their provenance.',
            );
        }
    }

    private function hasLegacyTimestampValues(): bool
    {
        return DB::table('users')
            ->whereRaw('email_verified_at IS NOT NULL OR created_at IS NOT NULL OR updated_at IS NOT NULL')
            ->exists()
            || DB::table('password_reset_tokens')->whereNotNull('created_at')->exists()
            || DB::table('personal_access_tokens')
                ->whereRaw('last_used_at IS NOT NULL OR expires_at IS NOT NULL OR created_at IS NOT NULL OR updated_at IS NOT NULL')
                ->exists()
            || DB::table('failed_jobs')->whereNotNull('failed_at')->exists();
    }
};
