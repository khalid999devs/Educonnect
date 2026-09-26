<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Tools\Enums\ToolReviewState;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tool> */
final class ToolFactory extends Factory
{
    protected $model = Tool::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(3);

        return [
            'tool_category_id' => ToolCategory::factory(),
            'name' => fake()->randomElement([
                'Research Notes Workspace',
                'Focused Study Timer',
                'Citation Review Assistant',
                'Project Board',
            ]),
            'purpose' => 'Helps students organize one bounded academic task.',
            'selection_reason' => 'Included because its documented workflow matches this study goal.',
            'use_cases' => [
                'Organize a course assignment',
                'Review progress before a deadline',
            ],
            'usage_guidance' => 'Start with one course or task, review the settings, and keep the original source available.',
            'limitations' => 'Review generated or automated output before using it for academic work.',
            'cost_note' => 'A free tier may be available; verify current pricing with the provider.',
            'privacy_note' => 'Check the provider privacy policy before uploading personal or unpublished academic material.',
            'external_url' => 'https://example.com/tools/'.$slug,
            'provenance' => 'EduConnect reviewed the provider documentation and public product information.',
            'state' => ToolReviewState::Draft->value,
            'last_reviewed_at' => null,
            'published_at' => null,
            'archived_at' => null,
            'version' => 1,
        ];
    }

    public function inReview(): static
    {
        return $this->state(fn (): array => [
            'state' => ToolReviewState::InReview->value,
            'last_reviewed_at' => null,
            'published_at' => null,
            'archived_at' => null,
        ]);
    }

    public function published(?DateTimeInterface $publishedAt = null): static
    {
        $publishedAt ??= now();

        return $this->state(fn (): array => [
            'state' => ToolReviewState::Published->value,
            'last_reviewed_at' => $publishedAt,
            'published_at' => $publishedAt,
            'archived_at' => null,
        ]);
    }

    public function archived(?DateTimeInterface $archivedAt = null): static
    {
        $archivedAt ??= now();

        return $this->state(fn (): array => [
            'state' => ToolReviewState::Archived->value,
            'last_reviewed_at' => $archivedAt,
            'published_at' => $archivedAt,
            'archived_at' => $archivedAt,
        ]);
    }
}
