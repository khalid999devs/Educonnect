<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Intake\Enums\IntakeArtifactKind;
use App\Domains\Intake\Models\IntakeArtifact;
use App\Domains\Intake\Models\IntakeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IntakeArtifact> */
final class IntakeArtifactFactory extends Factory
{
    protected $model = IntakeArtifact::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'intake_item_id' => IntakeItem::factory(),
            'kind' => IntakeArtifactKind::ExtractedText->value,
            'content_type' => 'text/plain',
            'byte_size' => 64,
            'text_content' => 'Neutral extracted intake text for isolated tests.',
            'metadata' => ['characters' => 49],
        ];
    }
}
