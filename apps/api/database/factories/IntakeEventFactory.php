<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Intake\Models\IntakeEvent;
use App\Domains\Intake\Models\IntakeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IntakeEvent> */
final class IntakeEventFactory extends Factory
{
    protected $model = IntakeEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'intake_item_id' => IntakeItem::factory(),
            'event' => 'created',
            'from_state' => null,
            'to_state' => 'uploaded_or_linked',
            'detail' => 'link intake created',
        ];
    }
}
