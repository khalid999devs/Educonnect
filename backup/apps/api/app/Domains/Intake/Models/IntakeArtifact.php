<?php

declare(strict_types=1);

namespace App\Domains\Intake\Models;

use App\Domains\Intake\Enums\IntakeArtifactKind;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\IntakeArtifactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $intake_item_id
 * @property IntakeArtifactKind $kind
 * @property string $content_type
 * @property int $byte_size
 * @property string|null $text_content
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read IntakeItem $item
 */
final class IntakeArtifact extends Model
{
    /** @use HasFactory<IntakeArtifactFactory> */
    use HasFactory, StoresUtcDateTimes;

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

    protected static function newFactory(): IntakeArtifactFactory
    {
        return IntakeArtifactFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => IntakeArtifactKind::class,
            'byte_size' => 'integer',
            'metadata' => 'array',
        ];
    }
}
