<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Tools\Models\Tool;
use Database\Seeders\GuidanceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_it_is_idempotent(): void
    {
        $this->seed(GuidanceCatalogSeeder::class);
        $before = Tool::query()->count();

        $this->seed(GuidanceCatalogSeeder::class);

        $this->assertSame($before, Tool::query()->count());
    }
}
