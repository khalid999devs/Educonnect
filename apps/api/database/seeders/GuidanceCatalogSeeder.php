<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Templates\Enums\TemplateBadge;
use App\Domains\Templates\Enums\TemplateFormat;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateVersion;
use App\Domains\Tools\Enums\ToolReviewState;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use Database\Seeders\Guidance\GuidanceCatalogData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The curated launch catalog, so a fresh install shows real guidance rather than
 * only empty states. Content lives in {@see GuidanceCatalogData}; this class owns
 * only the persistence rules.
 *
 * Upserts per natural key (category slug, tool/prompt/workflow/template title) so
 * the seeder can extend an existing catalog instead of refusing to run once any
 * tool exists. Running it twice changes nothing.
 *
 * One deliberate restriction: reviewed rows are never rewritten in place. The
 * catalog tables carry transition triggers - tools_content_edit_invalidates_review
 * and its siblings - which require published content to return to draft before it
 * changes, precisely so an unattended process cannot silently alter text a human
 * approved. This seeder therefore updates only rows still in draft and leaves
 * published rows to the admin review workflow.
 */
final class GuidanceCatalogSeeder extends Seeder
{
    private const REVIEWED_AT = '2026-07-01T09:00:00Z';

    public function run(): void
    {
        $reviewedAt = Carbon::parse(self::REVIEWED_AT);

        $categories = $this->seedCategories();
        $tools = $this->seedTools($categories, $reviewedAt);

        $this->seedPrompts($categories, $tools, $reviewedAt);
        $this->seedWorkflows($categories, $reviewedAt);
        $this->seedTemplates($categories, $reviewedAt);
    }

    /**
     * @return array<string, ToolCategory> keyed by slug
     */
    private function seedCategories(): array
    {
        $categories = [];

        foreach (GuidanceCatalogData::categories() as $data) {
            $category = ToolCategory::query()->where('slug', $data['slug'])->first();

            if (! $category instanceof ToolCategory) {
                $category = new ToolCategory;
                $category->forceFill([
                    'slug' => $data['slug'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'sort_order' => $data['sort_order'],
                ])->save();
            }

            $categories[$data['slug']] = $category;
        }

        return $categories;
    }

    /**
     * @param  array<string, ToolCategory>  $categories
     * @return array<string, Tool> keyed by tool name
     */
    private function seedTools(array $categories, Carbon $reviewedAt): array
    {
        $tools = [];

        foreach (GuidanceCatalogData::tools() as $data) {
            $category = $categories[$data['category']] ?? null;

            if (! $category instanceof ToolCategory) {
                continue;
            }

            $tool = Tool::query()->where('name', $data['name'])->first();

            if ($tool instanceof Tool) {
                $tools[$data['name']] = $tool;

                continue;
            }

            $tool = new Tool;
            $tool->forceFill([
                'tool_category_id' => $category->getKey(),
                'name' => $data['name'],
                'purpose' => $data['purpose'],
                'selection_reason' => $data['selection_reason'],
                'use_cases' => $data['use_cases'],
                'usage_guidance' => $data['usage_guidance'],
                'limitations' => $data['limitations'],
                'cost_note' => $data['cost_note'],
                'privacy_note' => $data['privacy_note'],
                'external_url' => $data['external_url'],
                'provenance' => $data['provenance'],
                'state' => ToolReviewState::Published->value,
                'last_reviewed_at' => $reviewedAt,
                'published_at' => $reviewedAt,
                'archived_at' => null,
                'version' => 1,
            ])->save();

            $tools[$data['name']] = $tool;
        }

        return $tools;
    }

    /**
     * @param  array<string, ToolCategory>  $categories
     * @param  array<string, Tool>  $tools
     */
    private function seedPrompts(array $categories, array $tools, Carbon $reviewedAt): void
    {
        foreach (GuidanceCatalogData::prompts() as $data) {
            $category = $categories[$data['category']] ?? null;

            if (! $category instanceof ToolCategory) {
                continue;
            }

            $prompt = PromptTemplate::query()->where('title', $data['title'])->first();

            if (! $prompt instanceof PromptTemplate) {
                $prompt = new PromptTemplate;
                $prompt->forceFill([
                    'tool_category_id' => $category->getKey(),
                    'title' => $data['title'],
                    'purpose' => $data['purpose'],
                    'template_body' => $data['template_body'],
                    'placeholders' => $data['placeholders'],
                    'expected_output' => $data['expected_output'],
                    'integrity_note' => $data['integrity_note'],
                    'provenance' => $data['provenance'],
                    'state' => GuidanceReviewState::Published->value,
                    'last_reviewed_at' => $reviewedAt,
                    'published_at' => $reviewedAt,
                    'archived_at' => null,
                    'version' => 1,
                ])->save();
            }

            $relatedIds = [];

            foreach ($data['related_tools'] as $toolName) {
                $related = $tools[$toolName] ?? null;

                if ($related instanceof Tool) {
                    $relatedIds[] = (int) $related->getKey();
                }
            }

            // syncWithoutDetaching leaves an admin's own curation intact.
            if ($relatedIds !== []) {
                $prompt->relatedTools()->syncWithoutDetaching($relatedIds);
            }
        }
    }

    /**
     * @param  array<string, ToolCategory>  $categories
     */
    private function seedWorkflows(array $categories, Carbon $reviewedAt): void
    {
        foreach (GuidanceCatalogData::workflows() as $data) {
            $category = $categories[$data['category']] ?? null;

            if (! $category instanceof ToolCategory) {
                continue;
            }

            $workflow = WorkflowRecipe::query()->where('title', $data['title'])->first();

            if ($workflow instanceof WorkflowRecipe) {
                continue;
            }

            $workflow = new WorkflowRecipe;
            $workflow->forceFill([
                'tool_category_id' => $category->getKey(),
                'title' => $data['title'],
                'goal' => $data['goal'],
                'expected_outcome' => $data['expected_outcome'],
                'integrity_note' => $data['integrity_note'],
                'provenance' => $data['provenance'],
                'state' => GuidanceReviewState::Published->value,
                'last_reviewed_at' => $reviewedAt,
                'published_at' => $reviewedAt,
                'archived_at' => null,
                'version' => 1,
            ])->save();

            foreach ($data['steps'] as $index => $step) {
                $model = new WorkflowStep;
                $model->forceFill([
                    'workflow_recipe_id' => $workflow->getKey(),
                    'step_number' => $index + 1,
                    'title' => $step['title'],
                    'instruction' => $step['instruction'],
                    'tool_id' => null,
                    'prompt_template_id' => null,
                    'template_id' => null,
                    'destination_action' => $step['destination_action'],
                ])->save();
            }
        }
    }

    /**
     * Templates carry an extra rule: a version row may only be added while the
     * template is a draft, and publication requires at least one version. So a
     * new template is inserted as a draft, given its single version, then walked
     * draft -> in_review -> published one transition at a time.
     *
     * @param  array<string, ToolCategory>  $categories
     */
    private function seedTemplates(array $categories, Carbon $reviewedAt): void
    {
        foreach (GuidanceCatalogData::templates() as $data) {
            $category = $categories[$data['category']] ?? null;

            if (! $category instanceof ToolCategory) {
                continue;
            }

            $template = Template::query()->where('title', $data['title'])->first();

            if ($template instanceof Template && $template->state === GuidanceReviewState::Published) {
                continue;
            }

            if (! $template instanceof Template) {
                $template = new Template;
                $template->forceFill([
                    'tool_category_id' => $category->getKey(),
                    'title' => $data['title'],
                    'summary' => $data['summary'],
                    'integrity_note' => $data['integrity_note'],
                    'provenance' => $data['provenance'],
                    'badge' => TemplateBadge::ApprovedFree->value,
                    'state' => GuidanceReviewState::Draft->value,
                    'last_reviewed_at' => null,
                    'published_at' => null,
                    'archived_at' => null,
                    'version' => 1,
                ])->save();
            }

            if ($template->state === GuidanceReviewState::Draft && $template->versions()->count() === 0) {
                (new TemplateVersion)->forceFill([
                    'template_id' => $template->getKey(),
                    'version_number' => 1,
                    'format' => TemplateFormat::Markdown->value,
                    'body' => trim($data['body']),
                    'change_note' => null,
                ])->save();
            }

            if ($template->state === GuidanceReviewState::Draft) {
                $template->forceFill(['state' => GuidanceReviewState::InReview->value])->save();
            }

            if ($template->state === GuidanceReviewState::InReview) {
                $template->forceFill([
                    'state' => GuidanceReviewState::Published->value,
                    'last_reviewed_at' => $reviewedAt,
                    'published_at' => $reviewedAt,
                ])->save();
            }
        }
    }
}
