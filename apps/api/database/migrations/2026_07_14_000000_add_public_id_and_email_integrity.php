<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CANONICAL_EMAIL_SQL = "LOWER(BTRIM(email, ' ' || CHR(9) || CHR(10) || CHR(11) || CHR(13)))";

    public function up(): void
    {
        $this->assertCanonicalEmailsAreMigratable('users');
        $this->assertCanonicalEmailsAreMigratable('password_reset_tokens');
        $this->assertLegacyTimestampProvenance();
        $this->assertNoOrphanedAuthenticatedSessions();
        $this->createUlidGenerator();

        Schema::table('users', function (Blueprint $table): void {
            $table->ulid('public_id')->nullable();
        });

        DB::statement('ALTER TABLE users ALTER COLUMN public_id SET DEFAULT educonnect_generate_ulid()');

        DB::table('users')
            ->select('id')
            ->chunkById(500, function (Collection $users): void {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['public_id' => DB::raw('educonnect_generate_ulid()')]);
                }
            });

        DB::table('users')->update([
            'email' => DB::raw(self::CANONICAL_EMAIL_SQL),
        ]);
        DB::table('password_reset_tokens')->update([
            'email' => DB::raw(self::CANONICAL_EMAIL_SQL),
        ]);

        DB::statement('ALTER TABLE users ALTER COLUMN public_id SET NOT NULL');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_public_id_unique UNIQUE (public_id)');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_public_id_format CHECK (public_id ~ '^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$')");
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_canonical CHECK (email = '.self::CANONICAL_EMAIL_SQL." AND email <> '')");
        DB::statement('ALTER TABLE password_reset_tokens ADD CONSTRAINT password_reset_tokens_email_canonical CHECK (email = '.self::CANONICAL_EMAIL_SQL." AND email <> '')");
        $this->createPublicIdImmutabilityTrigger();
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS users_public_id_immutable ON users');
        DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_user_public_id_update()');
        DB::statement('ALTER TABLE password_reset_tokens DROP CONSTRAINT IF EXISTS password_reset_tokens_email_canonical');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_canonical');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_public_id_format');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_public_id_unique');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('public_id');
        });

        DB::statement('DROP FUNCTION IF EXISTS educonnect_generate_ulid()');
    }

    private function assertCanonicalEmailsAreMigratable(string $table): void
    {
        if (DB::table($table)->whereRaw(self::CANONICAL_EMAIL_SQL." = ''")->exists()) {
            throw new RuntimeException("Cannot enforce canonical email integrity on [{$table}]: empty values exist.");
        }

        $hasDuplicates = DB::table($table)
            ->selectRaw(self::CANONICAL_EMAIL_SQL.' AS canonical_email')
            ->groupByRaw(self::CANONICAL_EMAIL_SQL)
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException("Cannot enforce canonical email integrity on [{$table}]: normalized duplicates exist.");
        }
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

    private function assertNoOrphanedAuthenticatedSessions(): void
    {
        $hasOrphans = DB::table('sessions')
            ->leftJoin('users', 'users.id', '=', 'sessions.user_id')
            ->whereNotNull('sessions.user_id')
            ->whereNull('users.id')
            ->exists();

        if ($hasOrphans) {
            throw new RuntimeException('Cannot enforce session ownership: orphaned authenticated sessions exist.');
        }
    }

    private function createUlidGenerator(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_generate_ulid()
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
            $$
            SQL);
    }

    private function createPublicIdImmutabilityTrigger(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_user_public_id_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
                    RAISE EXCEPTION 'users.public_id is immutable' USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER users_public_id_immutable
            BEFORE UPDATE OF public_id ON users
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_user_public_id_update()
            SQL);
    }
};
