<?php

declare(strict_types=1);

namespace Tests\Feature\Tools;

use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Tools\Models\UserToolPreference;
use App\Domains\Users\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ToolMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_identity_lifecycle_and_preference_integrity_are_database_enforced(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = ToolCategory::factory()->create(['slug' => 'integrity-tools']);
        $otherCategory = ToolCategory::factory()->create(['slug' => 'other-tools']);
        $published = Tool::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'name' => 'Published integrity tool',
        ]);
        $preference = UserToolPreference::factory()->create([
            'user_id' => $user->getKey(),
            'tool_id' => $published->getKey(),
            'state' => 'saved',
        ]);

        foreach ([$category->public_id, $published->public_id] as $publicId) {
            self::assertIsString($publicId);
            self::assertTrue(Str::isUlid($publicId));
            self::assertSame(strtolower($publicId), $publicId);
            self::assertMatchesRegularExpression(
                '/^[01234567][0123456789abcdefghjkmnpqrstvwxyz]{25}$/',
                $publicId,
            );
        }

        self::assertSame('public_id', $category->getRouteKeyName());
        self::assertSame('public_id', $published->getRouteKeyName());

        $this->assertQueryRejected('23514', fn () => DB::table('tool_categories')
            ->where('id', $category->getKey())
            ->update(['public_id' => strtolower((string) Str::ulid())]));
        $this->assertQueryRejected('23514', fn () => DB::table('tool_categories')
            ->where('id', $category->getKey())
            ->update(['slug' => 'changed-integrity-tools']));
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $published->getKey())
            ->update(['public_id' => strtolower((string) Str::ulid())]));
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $published->getKey())
            ->update(['name' => 'Unreviewed published edit']));
        $this->assertQueryRejected('23514', fn () => DB::table('user_tool_preferences')
            ->where('id', $preference->getKey())
            ->update(['user_id' => $other->getKey()]));
        $this->assertQueryRejected('23514', fn () => DB::table('user_tool_preferences')
            ->where('id', $preference->getKey())
            ->update(['tool_id' => Tool::factory()->published()->create()->getKey()]));
        $this->assertQueryRejected('23505', fn () => UserToolPreference::factory()->create([
            'user_id' => $user->getKey(),
            'tool_id' => $published->getKey(),
            'state' => 'dismissed',
        ]));
        $this->assertQueryRejected('23514', fn () => DB::table('user_tool_preferences')
            ->where('id', $preference->getKey())
            ->update(['state' => 'unknown']));

        $draft = Tool::factory()->create(['tool_category_id' => $category->getKey()]);
        DB::table('tools')->where('id', $draft->getKey())->update([
            'tool_category_id' => $otherCategory->getKey(),
            'updated_at' => now(),
        ]);
        $this->assertDatabaseHas('tools', [
            'id' => $draft->getKey(),
            'tool_category_id' => $otherCategory->getKey(),
            'state' => 'draft',
        ]);

        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $draft->getKey())
            ->update(['state' => 'published']));
        $inReview = Tool::factory()->inReview()->create();
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $inReview->getKey())
            ->update(['state' => 'published']));

        $reviewedAt = now()->startOfSecond();
        DB::table('tools')->where('id', $inReview->getKey())->update([
            'state' => 'published',
            'last_reviewed_at' => $reviewedAt,
            'published_at' => $reviewedAt,
            'version' => 2,
            'updated_at' => $reviewedAt,
        ]);
        $this->assertDatabaseHas('tools', [
            'id' => $inReview->getKey(),
            'state' => 'published',
            'version' => 2,
        ]);

        DB::table('tools')->where('id', $published->getKey())->update([
            'state' => 'draft',
            'name' => 'Revised draft integrity tool',
            'last_reviewed_at' => null,
            'published_at' => null,
            'archived_at' => null,
            'version' => 2,
            'updated_at' => now(),
        ]);
        $this->assertDatabaseHas('tools', [
            'id' => $published->getKey(),
            'state' => 'draft',
            'name' => 'Revised draft integrity tool',
            'last_reviewed_at' => null,
            'published_at' => null,
            'archived_at' => null,
            'version' => 2,
        ]);
    }

    public function test_catalog_content_bounds_and_review_metadata_are_database_enforced(): void
    {
        $category = ToolCategory::factory()->create();
        $draft = Tool::factory()->create(['tool_category_id' => $category->getKey()]);

        $this->assertQueryRejected('23514', fn () => ToolCategory::factory()->create([
            'slug' => 'Invalid Slug',
        ]));
        $this->assertQueryRejected('23514', fn () => DB::table('tool_categories')
            ->where('id', $category->getKey())
            ->update(['name' => '   ']));
        $this->assertQueryRejected('23514', fn () => DB::table('tool_categories')
            ->where('id', $category->getKey())
            ->update(['sort_order' => 65536]));
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $draft->getKey())
            ->update(['name' => '   ']));
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $draft->getKey())
            ->update(['external_url' => 'http://example.edu/unsafe']));
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $draft->getKey())
            ->update(['external_url' => 'https://user:password@example.edu/private']));
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $draft->getKey())
            ->update(['external_url' => 'https://[broken']));
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $draft->getKey())
            ->update(['use_cases' => json_encode([], JSON_THROW_ON_ERROR)]));
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $draft->getKey())
            ->update(['use_cases' => json_encode(['  untrimmed  '], JSON_THROW_ON_ERROR)]));
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $draft->getKey())
            ->update(['version' => 0]));

        $published = Tool::factory()->published()->create();
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $published->getKey())
            ->update(['last_reviewed_at' => null]));
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $published->getKey())
            ->update([
                'state' => 'archived',
                'archived_at' => $published->published_at->subSecond(),
            ]));
        $this->assertQueryRejected('23514', fn () => DB::table('tools')
            ->where('id', $published->getKey())
            ->update(['state' => 'in_review']));
    }

    public function test_catalog_datetimes_foreign_keys_and_access_indexes_match_the_postgresql_contract(): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('table_name', 'tool_categories')
                        ->whereIn('column_name', ['created_at', 'updated_at']);
                })->orWhere(function ($query): void {
                    $query->where('table_name', 'tools')
                        ->whereIn('column_name', [
                            'last_reviewed_at',
                            'published_at',
                            'archived_at',
                            'created_at',
                            'updated_at',
                        ]);
                })->orWhere(function ($query): void {
                    $query->where('table_name', 'user_tool_preferences')
                        ->whereIn('column_name', ['created_at', 'updated_at']);
                });
            })
            ->get(['table_name', 'column_name', 'data_type', 'datetime_precision']);

        self::assertCount(9, $columns);
        foreach ($columns as $column) {
            self::assertSame('timestamp with time zone', $column->data_type);
            self::assertSame(0, $column->datetime_precision);
        }

        $indexes = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->whereIn('indexname', [
                'tool_categories_catalog_order_idx',
                'tools_category_lookup_idx',
                'tools_admin_state_updated_cursor_idx',
                'tools_published_name_cursor_idx',
                'tools_published_category_name_cursor_idx',
                'tools_published_reviewed_cursor_idx',
                'user_tool_preferences_user_state_updated_idx',
                'user_tool_preferences_tool_user_idx',
            ])
            ->pluck('indexdef', 'indexname');

        self::assertCount(8, $indexes);
        self::assertStringContainsString(
            'lower((name)::text), public_id',
            strtolower((string) $indexes['tools_published_name_cursor_idx']),
        );
        $reviewedIndex = strtolower((string) $indexes['tools_published_reviewed_cursor_idx']);
        self::assertStringContainsString("(state)::text = 'published'::text", $reviewedIndex);
        self::assertStringContainsString('archived_at is null', $reviewedIndex);
        self::assertStringContainsString(
            '(user_id, state, updated_at DESC, tool_id)',
            (string) $indexes['user_tool_preferences_user_state_updated_idx'],
        );

        $deleteActions = DB::table('pg_constraint')
            ->whereIn('conname', [
                'tools_category_foreign',
                'user_tool_preferences_user_foreign',
                'user_tool_preferences_tool_foreign',
            ])
            ->pluck('confdeltype', 'conname');

        self::assertSame('r', $deleteActions['tools_category_foreign']);
        self::assertSame('c', $deleteActions['user_tool_preferences_user_foreign']);
        self::assertSame('a', $deleteActions['user_tool_preferences_tool_foreign']);
    }

    public function test_migration_rolls_back_atomically_when_empty_and_refuses_catalog_rows(): void
    {
        $migration = $this->migration();
        $statements = [];
        DB::listen(static function (QueryExecuted $query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $migration->down();
        self::assertContains('LOCK TABLE user_tool_preferences IN ACCESS EXCLUSIVE MODE', $statements);
        self::assertContains('LOCK TABLE tools IN ACCESS EXCLUSIVE MODE', $statements);
        self::assertContains('LOCK TABLE tool_categories IN ACCESS EXCLUSIVE MODE', $statements);
        self::assertFalse(Schema::hasTable('user_tool_preferences'));
        self::assertFalse(Schema::hasTable('tools'));
        self::assertFalse(Schema::hasTable('tool_categories'));

        $migration->up();
        self::assertTrue(Schema::hasTable('tool_categories'));
        self::assertTrue(Schema::hasTable('tools'));
        self::assertTrue(Schema::hasTable('user_tool_preferences'));

        $category = ToolCategory::factory()->create();

        try {
            $migration->down();
            self::fail('The tools catalog migration erased curated catalog data.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('category, tool, or user-preference data exists', $exception->getMessage());
        }

        self::assertTrue(Schema::hasTable('tool_categories'));
        self::assertTrue(Schema::hasTable('tools'));
        self::assertTrue(Schema::hasTable('user_tool_preferences'));
        $this->assertDatabaseHas('tool_categories', ['id' => $category->getKey()]);
    }

    private function migration(): Migration
    {
        $migration = require database_path(
            'migrations/2026_07_15_000010_create_tools_catalog.php',
        );
        self::assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    /** @param callable(): mixed $operation */
    private function assertQueryRejected(string $expectedSqlState, callable $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            self::assertSame($expectedSqlState, $exception->errorInfo[0] ?? null);

            return;
        }

        self::fail("Expected PostgreSQL to reject the statement with SQLSTATE {$expectedSqlState}.");
    }
}
