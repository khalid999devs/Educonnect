<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Intake\Enums\IntakeSuggestionKind;
use App\Domains\Intake\Enums\IntakeSuggestionStatus;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Models\IntakeSuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IntakeSuggestion> */
final class IntakeSuggestionFactory extends Factory
{
    protected $model = IntakeSuggestion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'intake_item_id' => IntakeItem::factory(),
            'kind' => IntakeSuggestionKind::Task->value,
            'payload' => [
                'title' => 'Submit the methods assignment',
                'description' => null,
                'due_at' => now()->addDays(10)->format('Y-m-d'),
                'course_public_id' => null,
                'url' => null,
            ],
            'schema_version' => 'v1',
            'confidence' => '0.700',
            'reason' => 'The document mentions an assignment next to a due date.',
            'status' => IntakeSuggestionStatus::Proposed->value,
        ];
    }

    public function resource(?string $url = 'https://intake.example.edu/reading-list'): static
    {
        return $this->state(fn (): array => [
            'kind' => IntakeSuggestionKind::Resource->value,
            'payload' => [
                'title' => 'Reading list source',
                'description' => null,
                'due_at' => null,
                'course_public_id' => null,
                'url' => $url,
            ],
            'confidence' => '0.500',
            'reason' => 'Saving the ingested source link keeps it findable in your workspace.',
        ]);
    }

    public function knowledgeItem(?string $url = 'https://intake.example.edu/spectral-methods.pdf'): static
    {
        return $this->state(fn (): array => [
            'kind' => IntakeSuggestionKind::KnowledgeItem->value,
            'payload' => [
                'title' => 'Spectral methods for boundary value problems',
                'description' => 'A survey chapter the seminar reading list points at.',
                'due_at' => null,
                'course_public_id' => null,
                'url' => $url,
            ],
            'schema_version' => 'v2',
            'confidence' => '0.620',
            'reason' => 'The reading list names this chapter as required study material.',
        ]);
    }
}
