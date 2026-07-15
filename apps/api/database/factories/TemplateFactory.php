<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Templates\Enums\TemplateBadge;
use App\Domains\Templates\Models\Template;
use App\Domains\Tools\Models\ToolCategory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Template> */
final class TemplateFactory extends Factory
{
    protected $model = Template::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tool_category_id' => ToolCategory::factory(),
            'title' => fake()->randomElement([
                'Weekly Study Plan Template',
                'Lecture Notes Outline',
                'Assignment Tracker Sheet',
                'Research Reading Log',
            ]),
            'summary' => 'A reusable structure a student can copy and adapt for one bounded academic activity.',
            'integrity_note' => 'Adapt the copied structure to your own work and follow your institution academic-integrity policy.',
            'provenance' => 'EduConnect drafted and reviewed this template with neutral placeholder content.',
            'badge' => TemplateBadge::ApprovedFree->value,
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
