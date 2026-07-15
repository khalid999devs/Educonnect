<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateVersion;
use App\Domains\Templates\Models\UserTemplateCopy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Templates\Concerns\InteractsWithTemplates;
use Tests\TestCase;

final class TemplateMigrationTest extends TestCase
{
    use InteractsWithTemplates;
    use RefreshDatabase;

    public function test_template_lifecycle_transitions_are_enforced_by_the_database(): void
    {
        $template = Template::factory()->create();

        $this->assertQueryRejected('23514', static fn () => DB::table('templates')
            ->where('id', $template->getKey())
            ->update(['state' => 'published', 'last_reviewed_at' => now(), 'published_at' => now()]));

        DB::table('templates')->where('id', $template->getKey())->update(['state' => 'in_review']);

        // Publication requires at least one immutable version.
        $this->assertQueryRejected('23514', static fn () => DB::table('templates')
            ->where('id', $template->getKey())
            ->update(['state' => 'published', 'last_reviewed_at' => now(), 'published_at' => now()]));

        DB::table('templates')->where('id', $template->getKey())->update(['state' => 'draft']);
        TemplateVersion::factory()->create(['template_id' => $template->getKey()]);
        DB::table('templates')->where('id', $template->getKey())->update(['state' => 'in_review']);
        DB::table('templates')->where('id', $template->getKey())->update([
            'state' => 'published',
            'last_reviewed_at' => now(),
            'published_at' => now(),
        ]);

        // Reviewed content cannot change without returning to draft.
        $this->assertQueryRejected('23514', static fn () => DB::table('templates')
            ->where('id', $template->getKey())
            ->update(['title' => 'Silently Edited Title']));

        // Identity stays immutable.
        $this->assertQueryRejected('23514', static fn () => DB::table('templates')
            ->where('id', $template->getKey())
            ->update(['public_id' => '01hzzzzzzzzzzzzzzzzzzzzzzz']));

        // Unknown badges are rejected.
        $this->assertQueryRejected('23514', static fn () => DB::table('templates')
            ->where('id', $template->getKey())
            ->update(['badge' => 'premium']));
    }

    public function test_template_versions_are_immutable_and_only_change_in_draft(): void
    {
        $template = Template::factory()->create();
        $version = TemplateVersion::factory()->create(['template_id' => $template->getKey()]);

        $this->assertQueryRejected('23514', static fn () => DB::table('template_versions')
            ->where('id', $version->getKey())
            ->update(['body' => 'Rewritten source body.']));

        DB::table('templates')->where('id', $template->getKey())->update(['state' => 'in_review']);
        DB::table('templates')->where('id', $template->getKey())->update([
            'state' => 'published',
            'last_reviewed_at' => now(),
            'published_at' => now(),
        ]);

        $this->assertQueryRejected('23514', static fn () => DB::table('template_versions')->insert([
            'template_id' => $template->getKey(),
            'version_number' => 2,
            'format' => 'markdown',
            'body' => 'A version smuggled past review.',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $this->assertQueryRejected('23514', static fn () => DB::table('template_versions')
            ->where('id', $version->getKey())
            ->delete());
    }

    public function test_copy_identity_is_immutable_and_referential_actions_hold(): void
    {
        $copy = UserTemplateCopy::factory()->create();

        $this->assertQueryRejected('23514', static fn () => DB::table('user_template_copies')
            ->where('id', $copy->getKey())
            ->update(['destination' => 'course']));
        $this->assertQueryRejected('23514', static fn () => DB::table('user_template_copies')
            ->where('id', $copy->getKey())
            ->update(['template_version_id' => 999_999]));

        // The referenced template and version refuse deletion while a copy exists.
        $this->assertQueryRejected('23503', static fn () => DB::table('template_versions')
            ->where('id', $copy->template_version_id)
            ->delete());
        $this->assertQueryRejected('23503', static fn () => DB::table('templates')
            ->where('id', $copy->template_id)
            ->delete());

        // Deleting the user cascades only that user's copy rows.
        DB::table('users')->where('id', $copy->user_id)->delete();
        $this->assertDatabaseMissing('user_template_copies', ['id' => $copy->getKey()]);
        $this->assertDatabaseHas('templates', ['id' => $copy->template_id]);
    }

    public function test_workflow_steps_accept_use_template_destinations_and_restrict_template_deletion(): void
    {
        $template = Template::factory()->create();
        $step = WorkflowStep::factory()->create([
            'template_id' => $template->getKey(),
            'destination_action' => 'use_template',
        ]);

        $this->assertDatabaseHas('workflow_steps', [
            'id' => $step->getKey(),
            'destination_action' => 'use_template',
        ]);
        $this->assertQueryRejected('23001', static fn () => DB::table('templates')
            ->where('id', $template->getKey())
            ->delete());
        $this->assertQueryRejected('23514', static fn () => DB::table('workflow_steps')
            ->where('id', $step->getKey())
            ->update(['destination_action' => 'unknown_destination']));
    }

    public function test_migration_rolls_back_atomically_when_empty_and_refuses_template_rows(): void
    {
        $migration = $this->migration();

        $migration->down();
        self::assertFalse(Schema::hasTable('templates'));
        self::assertFalse(Schema::hasTable('template_versions'));
        self::assertFalse(Schema::hasTable('user_template_preferences'));
        self::assertFalse(Schema::hasTable('user_template_copies'));
        self::assertFalse(Schema::hasColumn('workflow_steps', 'template_id'));

        $migration->up();
        self::assertTrue(Schema::hasTable('templates'));
        self::assertTrue(Schema::hasTable('user_template_copies'));
        self::assertTrue(Schema::hasColumn('workflow_steps', 'template_id'));

        $template = Template::factory()->create();

        try {
            $migration->down();
            self::fail('The template migration erased curated template data.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('template or user-copy data exists', $exception->getMessage());
        }

        self::assertTrue(Schema::hasTable('templates'));
        $this->assertDatabaseHas('templates', ['id' => $template->getKey()]);
    }

    private function migration(): Migration
    {
        $migration = require database_path(
            'migrations/2026_07_15_000012_create_templates_and_editable_copies.php',
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
