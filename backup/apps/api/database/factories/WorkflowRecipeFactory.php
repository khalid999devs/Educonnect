<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Tools\Models\ToolCategory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkflowRecipe> */
final class WorkflowRecipeFactory extends Factory
{
    protected $model = WorkflowRecipe::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tool_category_id' => ToolCategory::factory(),
            'title' => fake()->randomElement([
                'Prepare a Literature Review',
                'Plan a Weekly Study Cycle',
                'Draft and Verify an Assignment',
                'Organize Research Sources',
            ]),
            'goal' => 'Move one bounded academic goal from intent to a reviewed result.',
            'expected_outcome' => 'A concrete artifact the student reviewed, with sources and next actions recorded.',
            'integrity_note' => 'Every generated or automated output must be reviewed by the student before academic use.',
            'provenance' => 'EduConnect drafted and reviewed this workflow against the related tool documentation.',
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
