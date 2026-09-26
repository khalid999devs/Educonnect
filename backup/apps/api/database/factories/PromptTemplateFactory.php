<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Tools\Models\ToolCategory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PromptTemplate> */
final class PromptTemplateFactory extends Factory
{
    protected $model = PromptTemplate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tool_category_id' => ToolCategory::factory(),
            'title' => fake()->randomElement([
                'Summarize a Lecture Reading',
                'Plan a Study Session',
                'Outline an Essay Draft',
                'Review Citation Accuracy',
            ]),
            'purpose' => 'Helps a student structure one bounded academic request.',
            'template_body' => 'Summarize {{source_title}} for the course {{course_name}}, focusing on {{focus_area}}.',
            'placeholders' => ['source_title', 'course_name', 'focus_area'],
            'expected_output' => 'A short structured summary with the key claims and any open questions.',
            'integrity_note' => 'Review the output yourself and follow your institution academic-integrity policy before submitting anything.',
            'provenance' => 'EduConnect drafted and reviewed this prompt against the related tool documentation.',
            'state' => GuidanceReviewState::Draft->value,
            'last_reviewed_at' => null,
            'published_at' => null,
            'archived_at' => null,
            'version' => 1,
        ];
    }

    public function inReview(): static
    {
        return $this->state(fn (): array => [
            'state' => GuidanceReviewState::InReview->value,
            'last_reviewed_at' => null,
            'published_at' => null,
            'archived_at' => null,
        ]);
    }

    public function published(?DateTimeInterface $publishedAt = null): static
    {
        $publishedAt ??= now();

        return $this->state(fn (): array => [
            'state' => GuidanceReviewState::Published->value,
            'last_reviewed_at' => $publishedAt,
            'published_at' => $publishedAt,
            'archived_at' => null,
        ]);
    }

    public function archived(?DateTimeInterface $archivedAt = null): static
    {
        $archivedAt ??= now();

        return $this->state(fn (): array => [
            'state' => GuidanceReviewState::Archived->value,
            'last_reviewed_at' => $archivedAt,
            'published_at' => $archivedAt,
            'archived_at' => $archivedAt,
        ]);
    }
}
