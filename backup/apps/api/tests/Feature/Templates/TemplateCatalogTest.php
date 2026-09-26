<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateVersion;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Templates\Concerns\InteractsWithTemplates;
use Tests\TestCase;

final class TemplateCatalogTest extends TestCase
{
    use InteractsWithTemplates;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_only_reviewed_published_templates_with_a_version_are_visible(): void
    {
        $user = User::factory()->create();
        $category = ToolCategory::factory()->create([
            'slug' => 'study-planning',
            'name' => 'Study planning',
        ]);
        $reviewedAt = CarbonImmutable::parse('2026-07-10T08:00:00Z');
        $published = $this->publishedTemplate([
            'tool_category_id' => $category->getKey(),
            'title' => 'Weekly Study Plan',
            'summary' => 'A weekly structure for bounded study blocks.',
        ], [
            'body' => "## Week\n\nPlan each block and review it afterwards.",
            'version_number' => 1,
        ], $reviewedAt);

        Template::factory()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Draft template',
        ]);
        Template::factory()->inReview()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Template under review',
        ]);
        Template::factory()->archived()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Archived template',
        ]);
        Template::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Published without any version',
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/templates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->public_id)
            ->assertJsonPath('data.0.title', 'Weekly Study Plan')
            ->assertJsonPath('data.0.category.key', 'study-planning')
            ->assertJsonPath('data.0.badge', 'approved_free')
            ->assertJsonPath('data.0.summary', 'A weekly structure for bounded study blocks.')
            ->assertJsonPath('data.0.latest_version.number', 1)
            ->assertJsonPath('data.0.latest_version.format', 'markdown')
            ->assertJsonPath('data.0.viewer_state.saved', false)
            ->assertJsonPath('data.0.viewer_state.dismissed', false)
            ->assertJsonPath('data.0.viewer_state.active_copy_count', 0);
    }

    public function test_template_detail_serves_the_latest_version_as_preview_and_conceals_hidden_records(): void
    {
        $user = User::factory()->create();
        $category = ToolCategory::factory()->create(['slug' => 'writing-support']);
        $template = Template::factory()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Essay Outline',
        ]);
        TemplateVersion::factory()->create([
            'template_id' => $template->getKey(),
            'version_number' => 1,
            'body' => 'First version body.',
        ]);
        TemplateVersion::factory()->create([
            'template_id' => $template->getKey(),
            'version_number' => 2,
            'body' => 'Second version body.',
            'change_note' => 'Clarified the outline sections.',
        ]);
        DB::table('templates')->where('id', $template->getKey())->update(['state' => 'in_review']);
        DB::table('templates')->where('id', $template->getKey())->update([
            'state' => 'published',
            'last_reviewed_at' => now(),
            'published_at' => now(),
        ]);

        $draft = Template::factory()->create(['tool_category_id' => $category->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/templates/{$template->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $template->public_id)
            ->assertJsonPath('data.latest_version.number', 2)
            ->assertJsonPath('data.latest_version.body', 'Second version body.');

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/templates/{$draft->public_id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_guidance_bundle_includes_templates_and_steps_render_only_published_templates(): void
    {
        $user = User::factory()->create();
        $category = ToolCategory::factory()->create(['slug' => 'goal-guidance']);
        $published = $this->publishedTemplate([
            'tool_category_id' => $category->getKey(),
            'title' => 'Guided Study Template',
        ]);
        $hidden = Template::factory()->create(['tool_category_id' => $category->getKey()]);
        $workflow = WorkflowRecipe::factory()->published()->create([
            'tool_category_id' => $category->getKey(),
            'title' => 'Guided Workflow',
        ]);
        WorkflowStep::factory()->create([
            'workflow_recipe_id' => $workflow->getKey(),
            'step_number' => 1,
            'template_id' => $published->getKey(),
            'destination_action' => 'use_template',
        ]);
        WorkflowStep::factory()->create([
            'workflow_recipe_id' => $workflow->getKey(),
            'step_number' => 2,
            'template_id' => $hidden->getKey(),
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/guidance?category=goal-guidance')
            ->assertOk()
            ->assertJsonCount(1, 'data.templates')
            ->assertJsonPath('data.templates.0.id', $published->public_id)
            ->assertJsonPath('data.workflows.0.steps.0.destination_action', 'use_template')
            ->assertJsonPath('data.workflows.0.steps.0.template.id', $published->public_id)
            ->assertJsonPath('data.workflows.0.steps.1.template', null);
    }

    public function test_templates_support_search_category_filter_and_both_sorts(): void
    {
        $user = User::factory()->create();
        $planning = ToolCategory::factory()->create(['slug' => 'planning']);
        $research = ToolCategory::factory()->create(['slug' => 'research']);
        $alpha = $this->publishedTemplate([
            'tool_category_id' => $planning->getKey(),
            'title' => 'Alpha Planner',
            'summary' => 'Structure for weekly planning.',
        ], [], CarbonImmutable::parse('2026-07-01T08:00:00Z'));
        $beta = $this->publishedTemplate([
            'tool_category_id' => $research->getKey(),
            'title' => 'Beta Reading Log',
            'summary' => 'A structured research reading log.',
        ], [], CarbonImmutable::parse('2026-07-05T08:00:00Z'));
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/templates')
            ->assertOk()
            ->assertJsonPath('data.0.id', $alpha->public_id)
            ->assertJsonPath('data.1.id', $beta->public_id);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/templates?sort=-last_reviewed_at')
            ->assertOk()
            ->assertJsonPath('data.0.id', $beta->public_id);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/templates?category=research')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $beta->public_id);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/templates?search=reading+log')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $beta->public_id);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/templates?preference=unknown&per_page=51&unexpected=true')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => [
                'preference',
                'per_page',
                'unexpected',
            ]]]]);
    }
}
