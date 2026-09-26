<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support;

use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Models\IntakeEvent;
use App\Domains\Intake\Models\IntakeItem;

final class IntakeEventRecorder
{
    public function record(
        IntakeItem $item,
        string $event,
        ?IntakeState $from = null,
        ?IntakeState $to = null,
        ?string $detail = null,
    ): void {
        $record = new IntakeEvent;
        $record->forceFill([
            'intake_item_id' => $item->getKey(),
            'event' => $event,
            'from_state' => $from?->value,
            'to_state' => $to?->value,
            'detail' => $detail !== null && $detail !== '' ? mb_substr($detail, 0, 400) : null,
        ])->save();
    }
}
