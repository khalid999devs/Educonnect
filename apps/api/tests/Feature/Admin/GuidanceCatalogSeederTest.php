<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Templates\Models\Template;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Tools\Support\PublishedToolVisibility;
use Database\Seeders\GuidanceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class GuidanceCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_published_launch_catalog(): void
    {
        $this->seed(GuidanceCatalogSeeder::class);

        $this->assertGreaterThan(0, Tool::query()->where('state', 'published')->count());
        $this->assertGreaterThan(0, PromptTemplate::query()->where('state', 'published')->count());
        $this->assertGreaterThan(0, WorkflowRecipe::query()->where('state', 'published')->count());

        // A seeded prompt carries its related tools, and a workflow carries steps.
        $prompt = PromptTemplate::query()->where('state', 'published')->firstOrFail();
        $this->assertGreaterThan(0, $prompt->relatedTools()->count());

        $workflow = WorkflowRecipe::query()->where('state', 'published')->firstOrFail();
        $this->assertGreaterThan(0, $workflow->steps()->count());
    }

    public function test_it_meets_the_catalog_breadth_minimums(): void
    {
        $this->seed(GuidanceCatalogSeeder::class);

        $this->assertGreaterThanOrEqual(10, ToolCategory::query()->count());
        $this->assertGreaterThanOrEqual(40, Tool::query()->where('state', 'published')->count());
        $this->assertGreaterThanOrEqual(20, PromptTemplate::query()->where('state', 'published')->count());
        $this->assertGreaterThanOrEqual(10, WorkflowRecipe::query()->where('state', 'published')->count());
        $this->assertGreaterThanOrEqual(12, Template::query()->where('state', 'published')->count());
    }

    public function test_every_seeded_tool_is_visible_to_students(): void
    {
        $this->seed(GuidanceCatalogSeeder::class);

        $total = Tool::query()->count();

        $visibleQuery = Tool::query();
        PublishedToolVisibility::apply($visibleQuery);

        $this->assertSame($total, $visibleQuery->count(), 'Every seeded tool must satisfy PublishedToolVisibility.');

        foreach (Tool::query()->cursor() as $tool) {
            $this->assertTrue(
                PublishedToolVisibility::allows($tool),
                "Tool [{$tool->name}] does not satisfy PublishedToolVisibility.",
            );
        }
    }

    public function test_every_seeded_tool_carries_curation_transparency(): void
    {
        $this->seed(GuidanceCatalogSeeder::class);

        foreach (Tool::query()->cursor() as $tool) {
            foreach (['provenance', 'cost_note', 'privacy_note', 'limitations'] as $field) {
                $value = $tool->getAttribute($field);

                $this->assertIsString($value);
                $this->assertGreaterThan(
                    20,
                    mb_strlen($value),
                    "Tool [{$tool->name}] has a placeholder-length {$field}.",
                );
            }

            $this->assertNotSame([], $tool->use_cases);
        }
    }

    public function test_every_seeded_tool_belongs_to_a_seeded_category(): void
    {
        $this->seed(GuidanceCatalogSeeder::class);

        $categoryIds = ToolCategory::query()->pluck('id')->all();

        $this->assertSame(
            0,
            Tool::query()->whereNotIn('tool_category_id', $categoryIds)->count(),
        );
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(GuidanceCatalogSeeder::class);

        $before = [
            'categories' => ToolCategory::query()->count(),
            'tools' => Tool::query()->count(),
            'prompts' => PromptTemplate::query()->count(),
            'workflows' => WorkflowRecipe::query()->count(),
            'templates' => Template::query()->count(),
        ];
        $toolTimestamps = Tool::query()->orderBy('id')->pluck('updated_at', 'id')->all();

        $this->seed(GuidanceCatalogSeeder::class);

        $after = [
            'categories' => ToolCategory::query()->count(),
            'tools' => Tool::query()->count(),
            'prompts' => PromptTemplate::query()->count(),
            'workflows' => WorkflowRecipe::query()->count(),
            'templates' => Template::query()->count(),
        ];

        $this->assertSame($before, $after);
        $this->assertEquals($toolTimestamps, Tool::query()->orderBy('id')->pluck('updated_at', 'id')->all());
    }

    public function test_it_extends_a_catalog_that_already_has_tools(): void
    {
        // The old seeder bailed out entirely once any tool existed, so a partially
        // seeded catalog could never be topped up.
        $this->seed(GuidanceCatalogSeeder::class);

        $keep = Tool::query()->orderBy('id')->firstOrFail();

        // Clear the dependants first; the catalog foreign keys are RESTRICT.
        DB::table('prompt_template_tool')->delete();
        PromptTemplate::query()->delete();
        Tool::query()->whereKeyNot($keep->getKey())->delete();

        $this->assertSame(1, Tool::query()->count());

        $this->seed(GuidanceCatalogSeeder::class);

        $this->assertGreaterThanOrEqual(40, Tool::query()->count());
        $this->assertGreaterThanOrEqual(20, PromptTemplate::query()->count());
    }
}
