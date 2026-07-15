<?php

declare(strict_types=1);

namespace Tests\Feature\Guidance;

use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\UserPromptCopy;
use App\Domains\Guidance\Models\UserPromptPreference;
use App\Domains\Guidance\Models\UserWorkflowPreference;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GuidanceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guidance_identity_lifecycle_and_preference_integrity_are_database_enforced(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = ToolCategory::factory()->create(['slug' => 'integrity-guidance']);
        $prompt = PromptTemplate::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
        ]);
        $workflow = WorkflowRecipe::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
        ]);
        $preference = UserPromptPreference::factory()->create([
            'user_id' => $user->getKey(),
            'prompt_template_id' => $prompt->getKey(),
            'state' => 'saved',
        ]);
        $workflowPreference = UserWorkflowPreference::factory()->create([
            'user_id' => $user->getKey(),
            'workflow_recipe_id' => $workflow->getKey(),
            'state' => 'saved',
        ]);

        foreach ([$prompt->public_id, $workflow->public_id] as $publicId) {
            self::assertIsString($publicId);
            self::assertTrue(Str::isUlid($publicId));
            self::assertSame(strtolower($publicId), $publicId);
        }

        $this->assertQueryRejected('23514', fn () => DB::table('prompt_templates')
            ->where('id', $prompt->getKey())
            ->update(['public_id' => strtolower((string) Str::ulid())]));
        $this->assertQueryRejected('23514', fn () => DB::table('workflow_recipes')
            ->where('id', $workflow->getKey())
            ->update(['public_id' => strtolower((string) Str::ulid())]));
        $this->assertQueryRejected('23514', fn () => DB::table('prompt_templates')
            ->where('id', $prompt->getKey())
            ->update(['title' => 'Unreviewed published edit']));
        $this->assertQueryRejected('23514', fn () => DB::table('workflow_recipes')
            ->where('id', $workflow->getKey())
            ->update(['title' => 'Unreviewed published edit']));
        $this->assertQueryRejected('23514', fn () => DB::table('user_prompt_preferences')
            ->where('id', $preference->getKey())
            ->update(['user_id' => $other->getKey()]));
        $this->assertQueryRejected('23514', fn () => DB::table('user_workflow_preferences')
            ->where('id', $workflowPreference->getKey())
            ->update(['user_id' => $other->getKey()]));
        $this->assertQueryRejected('23505', fn () => UserPromptPreference::factory()->create([
            'user_id' => $user->getKey(),
            'prompt_template_id' => $prompt->getKey(),
            'state' => 'dismissed',
        ]));
        $this->assertQueryRejected('23505', fn () => UserWorkflowPreference::factory()->create([
            'user_id' => $user->getKey(),
            'workflow_recipe_id' => $workflow->getKey(),
            'state' => 'dismissed',
        ]));
        $this->assertQueryRejected('23514', fn () => DB::table('user_prompt_preferences')
            ->where('id', $preference->getKey())
            ->update(['state' => 'unknown']));

        $copy = UserPromptCopy::factory()->create([
            'user_id' => $user->getKey(),
            'prompt_template_id' => $prompt->getKey(),
        ]);
        $this->assertQueryRejected('23514', fn () => DB::table('user_prompt_copies')
            ->where('id', $copy->getKey())
            ->update(['copy_count' => 0]));
        $this->assertQueryRejected('23514', fn () => DB::table('user_prompt_copies')
            ->where('id', $copy->getKey())
            ->update(['first_copied_at' => now()->addDay()]));
        $this->assertQueryRejected('23505', fn () => UserPromptCopy::factory()->create([
            'user_id' => $user->getKey(),
            'prompt_template_id' => $prompt->getKey(),
        ]));
    }

    public function test_publication_transitions_require_related_tools_and_ordered_steps(): void
    {
        $category = ToolCategory::factory()->create();
        $tool = Tool::factory()->published()->create(['tool_category_id' => $category->getKey()]);

        $prompt = PromptTemplate::factory()->inReview()->create([
            'tool_category_id' => $category->getKey(),
        ]);
        $this->assertQueryRejected('23514', fn () => DB::table('prompt_templates')
            ->where('id', $prompt->getKey())
            ->update([
                'state' => 'published',
                'last_reviewed_at' => now(),
                'published_at' => now(),
                'updated_at' => now(),
            ]));

        $prompt->relatedTools()->attach($tool->getKey());
        $reviewedAt = now()->startOfSecond();
        DB::table('prompt_templates')->where('id', $prompt->getKey())->update([
            'state' => 'published',
            'last_reviewed_at' => $reviewedAt,
            'published_at' => $reviewedAt,
            'updated_at' => $reviewedAt,
        ]);
        $this->assertDatabaseHas('prompt_templates', [
            'id' => $prompt->getKey(),
            'state' => 'published',
        ]);

        $workflow = WorkflowRecipe::factory()->inReview()->create([
            'tool_category_id' => $category->getKey(),
        ]);
        $this->assertQueryRejected('23514', fn () => DB::table('workflow_recipes')
            ->where('id', $workflow->getKey())
            ->update([
                'state' => 'published',
                'last_reviewed_at' => now(),
                'published_at' => now(),
                'updated_at' => now(),
            ]));

        WorkflowStep::factory()->create(['workflow_recipe_id' => $workflow->getKey()]);
        DB::table('workflow_recipes')->where('id', $workflow->getKey())->update([
            'state' => 'published',
            'last_reviewed_at' => $reviewedAt,
            'published_at' => $reviewedAt,
            'updated_at' => $reviewedAt,
        ]);
        $this->assertDatabaseHas('workflow_recipes', [
            'id' => $workflow->getKey(),
            'state' => 'published',
        ]);

        $draft = PromptTemplate::factory()->create(['tool_category_id' => $category->getKey()]);
        $this->assertQueryRejected('23514', fn () => DB::table('prompt_templates')
            ->where('id', $draft->getKey())
            ->update(['state' => 'published']));
        $this->assertQueryRejected('23514', fn () => DB::table('workflow_recipes')
            ->where('id', $workflow->getKey())
            ->update(['state' => 'in_review']));
    }

    public function test_guidance_content_bounds_and_step_references_are_database_enforced(): void
    {
        $category = ToolCategory::factory()->create();
        $draft = PromptTemplate::factory()->create(['tool_category_id' => $category->getKey()]);
        $workflow = WorkflowRecipe::factory()->create(['tool_category_id' => $category->getKey()]);
        $step = WorkflowStep::factory()->create(['workflow_recipe_id' => $workflow->getKey()]);

        $this->assertQueryRejected('23514', fn () => DB::table('prompt_templates')
            ->where('id', $draft->getKey())
            ->update(['title' => '   ']));
        $this->assertQueryRejected('23514', fn () => DB::table('prompt_templates')
            ->where('id', $draft->getKey())
            ->update(['placeholders' => json_encode([], JSON_THROW_ON_ERROR)]));
        $this->assertQueryRejected('23514', fn () => DB::table('prompt_templates')
            ->where('id', $draft->getKey())
            ->update(['placeholders' => json_encode(['  untrimmed  '], JSON_THROW_ON_ERROR)]));
        $this->assertQueryRejected('23514', fn () => DB::table('prompt_templates')
            ->where('id', $draft->getKey())
            ->update(['version' => 0]));
        $this->assertQueryRejected('23514', fn () => DB::table('workflow_steps')
            ->where('id', $step->getKey())
            ->update(['step_number' => 0]));
        $this->assertQueryRejected('23514', fn () => DB::table('workflow_steps')
            ->where('id', $step->getKey())
            ->update(['step_number' => 51]));
        $this->assertQueryRejected('23514', fn () => DB::table('workflow_steps')
            ->where('id', $step->getKey())
            ->update(['destination_action' => 'unknown_destination']));
        $this->assertQueryRejected('23505', fn () => WorkflowStep::factory()->create([
            'workflow_recipe_id' => $workflow->getKey(),
            'step_number' => (int) $step->step_number,
        ]));

        $referencedTool = Tool::factory()->published()->create();
        DB::table('workflow_steps')->where('id', $step->getKey())->update([
            'tool_id' => $referencedTool->getKey(),
            'updated_at' => now(),
        ]);
        $this->assertQueryRejected('23001', fn () => DB::table('tools')
            ->where('id', $referencedTool->getKey())
            ->delete());

        $relatedPrompt = PromptTemplate::factory()->create();
        DB::table('workflow_steps')->where('id', $step->getKey())->update([
            'prompt_template_id' => $relatedPrompt->getKey(),
            'updated_at' => now(),
        ]);
        $this->assertQueryRejected('23001', fn () => DB::table('prompt_templates')
            ->where('id', $relatedPrompt->getKey())
            ->delete());

        DB::table('workflow_recipes')->where('id', $workflow->getKey())->delete();
        $this->assertDatabaseMissing('workflow_steps', ['id' => $step->getKey()]);
    }

    public function test_migration_rolls_back_atomically_when_empty_and_refuses_guidance_rows(): void
    {
        $migration = $this->migration();

        $migration->down();
        self::assertFalse(Schema::hasTable('prompt_templates'));
        self::assertFalse(Schema::hasTable('prompt_template_tool'));
        self::assertFalse(Schema::hasTable('workflow_recipes'));
        self::assertFalse(Schema::hasTable('workflow_steps'));
        self::assertFalse(Schema::hasTable('user_prompt_preferences'));
        self::assertFalse(Schema::hasTable('user_prompt_copies'));
        self::assertFalse(Schema::hasTable('user_workflow_preferences'));

        $migration->up();
        self::assertTrue(Schema::hasTable('prompt_templates'));
        self::assertTrue(Schema::hasTable('workflow_recipes'));
        self::assertTrue(Schema::hasTable('user_workflow_preferences'));

        $prompt = PromptTemplate::factory()->create();

        try {
            $migration->down();
            self::fail('The guidance migration erased curated prompt or workflow data.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('guidance or user-preference data exists', $exception->getMessage());
        }

        self::assertTrue(Schema::hasTable('prompt_templates'));
        $this->assertDatabaseHas('prompt_templates', ['id' => $prompt->getKey()]);
    }

    private function migration(): Migration
    {
        $migration = require database_path(
            'migrations/2026_07_15_000011_create_prompts_and_workflow_recipes.php',
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
