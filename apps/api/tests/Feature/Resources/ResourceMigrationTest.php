<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Domains\Courses\Actions\DeleteCourseAction;
use App\Domains\Courses\Exceptions\AcademicStateConflict;
use App\Domains\Courses\Models\Course;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Users\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ResourceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_identity_exact_ownership_and_file_resource_integrity_are_database_enforced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $owner->getKey()]);
        $foreignCourse = Course::factory()->create(['user_id' => $other->getKey()]);
        $link = Resource::factory()->forCourse($course)->create();
        $file = Resource::factory()->file()->create(['user_id' => $owner->getKey()]);
        $storedFile = StoredFile::factory()->forResource($file)->create();
        $replacementUploadKey = str_ends_with((string) $storedFile->upload_key, str_repeat('a', 32))
            ? 'resources/v1/uploads/'.str_repeat('b', 32)
            : 'resources/v1/uploads/'.str_repeat('a', 32);
        $replacementObjectKey = str_ends_with((string) $storedFile->object_key, str_repeat('c', 32))
            ? 'resources/v1/objects/'.str_repeat('d', 32)
            : 'resources/v1/objects/'.str_repeat('c', 32);

        foreach ([$link->public_id, $file->public_id, $storedFile->public_id] as $publicId) {
            $this->assertIsString($publicId);
            $this->assertTrue(Str::isUlid($publicId));
            $this->assertSame(strtolower($publicId), $publicId);
            $this->assertMatchesRegularExpression(
                '/^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$/',
                $publicId,
            );
        }

        $this->assertSame('public_id', $link->getRouteKeyName());
        $this->assertSame('public_id', $storedFile->getRouteKeyName());

        $this->assertQueryRejected('23514', fn () => DB::table('resources')
            ->where('id', $link->getKey())
            ->update(['public_id' => strtolower((string) Str::ulid())]));
        $this->assertQueryRejected('23514', fn () => DB::table('resources')
            ->where('id', $link->getKey())
            ->update(['user_id' => $other->getKey()]));
        $this->assertQueryRejected('23514', fn () => DB::table('resources')
            ->where('id', $link->getKey())
            ->update(['kind' => ResourceKind::File->value, 'source_url' => null]));
        $this->assertQueryRejected('23503', fn () => DB::table('resources')
            ->where('id', $link->getKey())
            ->update(['course_id' => $foreignCourse->getKey()]));

        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['public_id' => strtolower((string) Str::ulid())]));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['user_id' => $other->getKey()]));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['resource_id' => $link->getKey()]));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['upload_key' => $replacementUploadKey]));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['object_key' => $replacementObjectKey]));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['original_name' => 'replacement.pdf']));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['expected_size' => 2048]));

        $this->assertQueryRejected('23514', fn () => StoredFile::factory()->forResource($link)->create());
        $unoccupiedFile = Resource::factory()->file()->create(['user_id' => $owner->getKey()]);
        $this->assertQueryRejected('23503', fn () => StoredFile::factory()->create([
            'user_id' => $other->getKey(),
            'resource_id' => $unoccupiedFile->getKey(),
        ]));
        $this->assertQueryRejected('23505', fn () => StoredFile::factory()->forResource($file)->create());

        $missingResourceId = (int) DB::table('resources')->max('id') + 1000;
        $this->assertQueryRejected('23514', function () use ($owner, $missingResourceId): void {
            DB::statement('SET CONSTRAINTS stored_files_owner_resource_foreign DEFERRED');
            DB::table('stored_files')->insert([
                'user_id' => $owner->getKey(),
                'resource_id' => $missingResourceId,
                'original_name' => 'deferred-child.pdf',
                'declared_mime_type' => 'application/pdf',
                'expected_size' => 1024,
                'sha256' => str_repeat('a', 64),
                'upload_key' => 'resources/v1/uploads/'.str_repeat('c', 32),
                'object_key' => 'resources/v1/objects/'.str_repeat('d', 32),
                'status' => StoredFileStatus::Pending->value,
                'upload_expires_at' => now()->addMinutes(15),
                'cleanup_after' => now()->addMinutes(30),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('resources')->insert([
                'id' => $missingResourceId,
                'user_id' => $owner->getKey(),
                'course_id' => null,
                'kind' => ResourceKind::Link->value,
                'title' => 'Deferred link resource',
                'description' => null,
                'topic_label' => null,
                'source_url' => 'https://example.edu/deferred-link',
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_resource_metadata_and_private_file_limits_are_database_enforced(): void
    {
        $link = Resource::factory()->create();
        $file = Resource::factory()->file()->create(['user_id' => $link->user_id]);
        $storedFile = StoredFile::factory()->forResource($file)->create();

        $this->assertQueryRejected('23514', fn () => DB::table('resources')
            ->where('id', $link->getKey())
            ->update(['title' => '   ']));
        $this->assertQueryRejected('23514', fn () => DB::table('resources')
            ->where('id', $link->getKey())
            ->update(['description' => '   ']));
        $this->assertQueryRejected('23514', fn () => DB::table('resources')
            ->where('id', $link->getKey())
            ->update(['topic_label' => '   ']));
        $this->assertQueryRejected('23514', fn () => DB::table('resources')
            ->where('id', $link->getKey())
            ->update(['source_url' => 'http://example.edu/private']));
        $this->assertQueryRejected('23514', fn () => DB::table('resources')
            ->where('id', $link->getKey())
            ->update(['source_url' => null]));
        $this->assertQueryRejected('23514', fn () => DB::table('resources')
            ->where('id', $file->getKey())
            ->update(['source_url' => 'https://example.edu/private']));
        $this->assertQueryRejected('23514', fn () => DB::table('resources')
            ->where('id', $link->getKey())
            ->update(['version' => 0]));

        foreach (['', '.', '..', '../private.pdf', 'folder/private.pdf', 'folder\\private.pdf', "private\n.pdf"] as $name) {
            $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
                ->where('id', $storedFile->getKey())
                ->update(['original_name' => $name]));
        }

        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['declared_mime_type' => 'application/x-php']));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['expected_size' => 0]));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['expected_size' => 26214401]));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['sha256' => str_repeat('A', 64)]));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['status' => 'unknown']));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['status' => StoredFileStatus::Ready->value]));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['cleanup_after' => null]));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['purge_ready_at' => now()]));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['upload_expires_at' => $storedFile->created_at]));

        $invalidUploadPath = Resource::factory()->file()->create(['user_id' => $link->user_id]);
        $this->assertQueryRejected('23514', fn () => StoredFile::factory()
            ->forResource($invalidUploadPath)
            ->create(['upload_key' => '../private/uploads/file.pdf']));
        $invalidObjectPath = Resource::factory()->file()->create(['user_id' => $link->user_id]);
        $this->assertQueryRejected('23514', fn () => StoredFile::factory()
            ->forResource($invalidObjectPath)
            ->create(['object_key' => 'resources/v1/objects/'.str_repeat('A', 32)]));
    }

    public function test_staging_cleanup_and_file_lifecycle_transitions_preserve_known_object_keys(): void
    {
        $file = Resource::factory()->file()->create();
        $storedFile = StoredFile::factory()->forResource($file)->create();
        $uploadKey = $storedFile->upload_key;
        $objectKey = $storedFile->object_key;
        $readyAt = $storedFile->created_at->addSecond();

        $this->assertIsString($uploadKey);
        $this->assertIsString($objectKey);

        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update([
                'verified_mime_type' => 'application/pdf',
                'verified_size' => 1023,
                'status' => StoredFileStatus::Ready->value,
                'ready_at' => $readyAt,
            ]));

        DB::table('stored_files')->where('id', $storedFile->getKey())->update([
            'verified_mime_type' => 'application/pdf',
            'verified_size' => 1024,
            'status' => StoredFileStatus::Ready->value,
            'ready_at' => $readyAt,
        ]);

        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['verified_mime_type' => 'image/png']));

        DB::table('stored_files')->where('id', $storedFile->getKey())->update([
            'upload_key' => null,
            'cleanup_after' => null,
        ]);
        $this->assertDatabaseHas('stored_files', [
            'id' => $storedFile->getKey(),
            'upload_key' => null,
            'object_key' => $objectKey,
            'status' => StoredFileStatus::Ready->value,
        ]);

        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['upload_key' => $uploadKey, 'cleanup_after' => now()->addMinutes(30)]));
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['status' => StoredFileStatus::DeletionPending->value]));

        DB::table('stored_files')->where('id', $storedFile->getKey())->update([
            'status' => StoredFileStatus::DeletionPending->value,
            'cleanup_after' => now()->addMinutes(30),
        ]);
        $this->assertDatabaseHas('stored_files', [
            'id' => $storedFile->getKey(),
            'upload_key' => null,
            'object_key' => $objectKey,
            'status' => StoredFileStatus::DeletionPending->value,
        ]);
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $storedFile->getKey())
            ->update(['status' => StoredFileStatus::Ready->value]));
    }

    public function test_course_and_user_deletion_refuse_private_dependencies_and_controlled_resource_purge_is_explicit(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->getKey()]);
        $courseResource = Resource::factory()->forCourse($course)->create();

        $this->assertQueryRejected('23503', fn () => DB::table('courses')
            ->where('id', $course->getKey())
            ->delete());

        try {
            $this->app->make(DeleteCourseAction::class)->execute($user, $course->public_id, 1);
            self::fail('Course deletion ignored a private resource dependency.');
        } catch (AcademicStateConflict $exception) {
            $this->assertSame(
                'The academic record cannot be changed in its current state.',
                $exception->getMessage(),
            );
        }

        $this->assertQueryRejected('23503', fn () => DB::table('users')
            ->where('id', $user->getKey())
            ->delete());
        $this->assertDatabaseHas('resources', ['id' => $courseResource->getKey()]);

        $file = Resource::factory()->file()->create(['user_id' => $user->getKey()]);
        $storedFile = StoredFile::factory()->forResource($file)->deletionPending(wasReady: false)->create();

        $this->assertQueryRejected('23514', fn () => $storedFile->deleteOrFail());

        DB::table('stored_files')->where('id', $storedFile->getKey())->update([
            'purge_ready_at' => now(),
        ]);
        $storedFile->deleteOrFail();
        $file->deleteOrFail();

        $this->assertDatabaseMissing('resources', ['id' => $file->getKey()]);
        $this->assertDatabaseMissing('stored_files', ['id' => $storedFile->getKey()]);

        $unretired = Resource::factory()->file()->create(['user_id' => $user->getKey()]);
        $unretiredFile = StoredFile::factory()->forResource($unretired)->create();
        $this->assertQueryRejected('23514', fn () => DB::table('stored_files')
            ->where('id', $unretiredFile->getKey())
            ->delete());
        $this->assertQueryRejected('23503', fn () => DB::table('resources')
            ->where('id', $unretired->getKey())
            ->delete());
    }

    public function test_resource_datetimes_foreign_keys_and_access_indexes_match_the_postgresql_contract(): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('table_name', 'resources')
                        ->whereIn('column_name', ['created_at', 'updated_at']);
                })->orWhere(function ($query): void {
                    $query->where('table_name', 'stored_files')
                        ->whereIn('column_name', [
                            'upload_expires_at',
                            'cleanup_after',
                            'cleanup_started_at',
                            'purge_ready_at',
                            'ready_at',
                            'created_at',
                            'updated_at',
                        ]);
                });
            })
            ->get(['table_name', 'column_name', 'data_type', 'datetime_precision']);

        $this->assertCount(9, $columns);

        foreach ($columns as $column) {
            $this->assertSame('timestamp with time zone', $column->data_type);
            $this->assertSame(0, $column->datetime_precision);
        }

        $indexes = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->whereIn('indexname', [
                'resources_owner_updated_cursor_idx',
                'resources_owner_kind_updated_cursor_idx',
                'resources_owner_course_updated_cursor_idx',
                'resources_owner_topic_updated_cursor_idx',
                'resources_owner_title_prefix_idx',
                'stored_files_cleanup_due_idx',
            ])
            ->pluck('indexdef', 'indexname');

        $this->assertCount(6, $indexes);
        $this->assertStringContainsString(
            '(user_id, updated_at DESC, public_id DESC)',
            (string) $indexes['resources_owner_updated_cursor_idx'],
        );
        $this->assertStringContainsString(
            '(user_id, kind, updated_at DESC, public_id DESC)',
            (string) $indexes['resources_owner_kind_updated_cursor_idx'],
        );
        $this->assertStringContainsString(
            '(user_id, course_id, updated_at DESC, public_id DESC)',
            (string) $indexes['resources_owner_course_updated_cursor_idx'],
        );
        $this->assertStringContainsString(
            '(user_id, topic_label, updated_at DESC, public_id DESC)',
            (string) $indexes['resources_owner_topic_updated_cursor_idx'],
        );
        $this->assertStringContainsString(
            'lower((title)::text) text_pattern_ops',
            strtolower((string) $indexes['resources_owner_title_prefix_idx']),
        );
        $this->assertStringContainsString(
            '(cleanup_after, id)',
            (string) $indexes['stored_files_cleanup_due_idx'],
        );

        $deleteActions = DB::table('pg_constraint')
            ->whereIn('conname', [
                'resources_owner_foreign',
                'resources_owner_course_foreign',
                'stored_files_owner_foreign',
                'stored_files_owner_resource_foreign',
            ])
            ->pluck('confdeltype', 'conname');

        $this->assertSame('a', $deleteActions['resources_owner_foreign']);
        $this->assertSame('a', $deleteActions['resources_owner_course_foreign']);
        $this->assertSame('a', $deleteActions['stored_files_owner_foreign']);
        $this->assertSame('a', $deleteActions['stored_files_owner_resource_foreign']);
    }

    public function test_migration_rolls_back_atomically_when_empty_and_refuses_private_rows(): void
    {
        $migration = $this->migration();
        $statements = [];
        DB::listen(static function (QueryExecuted $query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $migration->down();
        $this->assertContains('LOCK TABLE resources IN ACCESS EXCLUSIVE MODE', $statements);
        $this->assertContains('LOCK TABLE stored_files IN ACCESS EXCLUSIVE MODE', $statements);
        $this->assertFalse(Schema::hasTable('stored_files'));
        $this->assertFalse(Schema::hasTable('resources'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('resources'));
        $this->assertTrue(Schema::hasTable('stored_files'));

        $resource = Resource::factory()->create();

        try {
            $migration->down();
            self::fail('The resource migration erased private resource data.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('private resource or file data exists', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('resources'));
        $this->assertTrue(Schema::hasTable('stored_files'));
        $this->assertDatabaseHas('resources', ['id' => $resource->getKey()]);
    }

    private function migration(): Migration
    {
        $migration = require database_path(
            'migrations/2026_07_14_000009_create_resource_storage_foundation.php',
        );
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    /** @param callable(): mixed $operation */
    private function assertQueryRejected(string $expectedSqlState, callable $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            $this->assertSame($expectedSqlState, $exception->errorInfo[0] ?? null);

            return;
        }

        self::fail("Expected PostgreSQL to reject the statement with SQLSTATE {$expectedSqlState}.");
    }
}
