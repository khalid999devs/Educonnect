<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Guidance\Enums\WorkflowDestinationAction;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A small, honest launch catalog so a fresh install shows real guidance rather
 * than only empty states. Every entry is generic (no named vendors or endorsements)
 * and published. Idempotent: it skips anything already present.
 */
final class GuidanceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (Tool::query()->exists()) {
            return;
        }

        $reviewedAt = Carbon::parse('2026-07-01T09:00:00Z');

        $studyPlanning = $this->category('study-planning', 'Study planning', 'Plan and revise effectively.', 10);
        $academicWriting = $this->category('academic-writing', 'Academic writing', 'Structure and cite your writing.', 20);

        $timer = $this->tool($studyPlanning->getKey(), $reviewedAt, [
            'name' => 'Focused Study Timer',
            'purpose' => 'Break study into focused intervals with short breaks.',
            'selection_reason' => 'Chosen because timeboxing keeps one task in view at a time.',
            'use_cases' => ['Time a revision block', 'Pace a problem set'],
            'usage_guidance' => 'Pick one task, set a 25-minute block, then take a short break.',
            'limitations' => 'A timer paces work; it cannot judge whether the work is correct.',
            'cost_note' => 'Free timers are widely available; no purchase is needed.',
            'privacy_note' => 'No personal or academic content needs to be entered into a timer.',
            'external_url' => 'https://study.example.edu/tools/focused-timer',
            'provenance' => 'Reviewed against common time-management guidance.',
        ]);

        $citations = $this->tool($academicWriting->getKey(), $reviewedAt, [
            'name' => 'Citation Checklist',
            'purpose' => 'Verify each source is cited consistently before submitting.',
            'selection_reason' => 'Chosen because a checklist catches missing or malformed references.',
            'use_cases' => ['Check a reference list', 'Confirm in-text citations'],
            'usage_guidance' => 'Work through each source and confirm author, year, title, and venue.',
            'limitations' => 'A checklist confirms format, not whether a source is appropriate.',
            'cost_note' => 'A checklist is free to use.',
            'privacy_note' => 'Do not paste unpublished work into any third-party checker.',
            'external_url' => 'https://study.example.edu/tools/citation-checklist',
            'provenance' => 'Reviewed against common academic-integrity guidance.',
        ]);

        $this->prompt($studyPlanning->getKey(), $reviewedAt, [$timer->getKey()], [
            'title' => 'Plan a Study Session',
            'purpose' => 'Turn a vague study goal into a bounded, timed plan.',
            'template_body' => 'Help me plan a {{minutes}}-minute study session for {{course_name}} focused on {{topic}}. List the steps and a short break.',
            'placeholders' => ['minutes', 'course_name', 'topic'],
            'expected_output' => 'A short ordered plan with time estimates and one break.',
            'integrity_note' => 'Use the plan to organize your own study; do the learning yourself.',
            'provenance' => 'Drafted and reviewed by the EduConnect learning team.',
        ]);

        $this->prompt($academicWriting->getKey(), $reviewedAt, [$citations->getKey()], [
            'title' => 'Outline an Essay',
            'purpose' => 'Produce a structured outline you then write yourself.',
            'template_body' => 'Give me a section-by-section outline for an essay on {{thesis}} for {{course_name}}, with one prompt per section to research.',
            'placeholders' => ['thesis', 'course_name'],
            'expected_output' => 'A labelled outline with a research prompt under each section.',
            'integrity_note' => 'An outline is a scaffold; write the essay in your own words and cite sources.',
            'provenance' => 'Drafted and reviewed by the EduConnect learning team.',
        ]);

        $this->workflow($studyPlanning->getKey(), $reviewedAt, [
            'title' => 'From Reading to Revision Notes',
            'goal' => 'Convert a chapter into revision notes with integrity.',
            'expected_outcome' => 'A set of notes in your own words with sources recorded.',
            'integrity_note' => 'Every step keeps the original source attributed and reviewed.',
            'provenance' => 'Curated by the EduConnect learning team.',
        ], [
            ['title' => 'Read and highlight', 'instruction' => 'Skim the chapter, then mark the load-bearing claims.', 'destination_action' => WorkflowDestinationAction::SaveResource->value],
            ['title' => 'Summarize each section', 'instruction' => 'Write one sentence per section in your own words.', 'destination_action' => null],
            ['title' => 'Review and cite', 'instruction' => 'Check each note against the source and record the citation.', 'destination_action' => WorkflowDestinationAction::SaveResource->value],
        ]);
    }

    private function category(string $slug, string $name, string $description, int $sortOrder): ToolCategory
    {
        $existing = ToolCategory::query()->where('slug', $slug)->first();

        if ($existing instanceof ToolCategory) {
            return $existing;
        }

        $category = new ToolCategory;
        $category->forceFill([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'sort_order' => $sortOrder,
        ])->save();

        return $category;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function tool(int $categoryId, Carbon $reviewedAt, array $content): Tool
    {
        $tool = new Tool;
        $tool->forceFill([
            ...$content,
            'tool_category_id' => $categoryId,
            'state' => 'published',
            'last_reviewed_at' => $reviewedAt,
            'published_at' => $reviewedAt,
            'version' => 1,
        ])->save();

        return $tool;
    }

    /**
     * @param  list<int>  $relatedToolIds
     * @param  array<string, mixed>  $content
     */
    private function prompt(int $categoryId, Carbon $reviewedAt, array $relatedToolIds, array $content): PromptTemplate
    {
        $prompt = new PromptTemplate;
        $prompt->forceFill([
            ...$content,
            'tool_category_id' => $categoryId,
            'state' => 'published',
            'last_reviewed_at' => $reviewedAt,
            'published_at' => $reviewedAt,
            'version' => 1,
        ])->save();

        $prompt->relatedTools()->sync($relatedToolIds);

        return $prompt;
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  list<array<string, mixed>>  $steps
     */
    private function workflow(int $categoryId, Carbon $reviewedAt, array $content, array $steps): WorkflowRecipe
    {
        $workflow = new WorkflowRecipe;
        $workflow->forceFill([
            ...$content,
            'tool_category_id' => $categoryId,
            'state' => 'published',
            'last_reviewed_at' => $reviewedAt,
            'published_at' => $reviewedAt,
            'version' => 1,
        ])->save();

        foreach ($steps as $index => $step) {
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

        return $workflow;
    }
}
