<?php

declare(strict_types=1);

namespace App\Domains\Intake\Models;

use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\IntakeEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $intake_item_id
 * @property string $event
 * @property string|null $from_state
 * @property string|null $to_state
 * @property string|null $detail
 * @property CarbonImmutable $created_at
 * @property-read IntakeItem $item
 */
final class IntakeEvent extends Model
{
    /** @use HasFactory<IntakeEventFactory> */
    use HasFactory, StoresUtcDateTimes;

    public const UPDATED_AT = null;

    protected $guarded = [
        'id',
        'intake_item_id',
    ];

    protected $hidden = [
        'id',
        'intake_item_id',
    ];

    /** @return BelongsTo<IntakeItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(IntakeItem::class, 'intake_item_id');
    }

    protected static function newFactory(): IntakeEventFactory
    {
        return IntakeEventFactory::new();
    }
}
