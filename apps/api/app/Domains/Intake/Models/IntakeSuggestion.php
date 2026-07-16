<?php

declare(strict_types=1);

namespace App\Domains\Intake\Models;

use App\Domains\Intake\Enums\IntakeSuggestionKind;
use App\Domains\Intake\Enums\IntakeSuggestionStatus;
use App\Domains\Planner\Models\Task;
use App\Domains\Resources\Models\Resource;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\IntakeSuggestionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $intake_item_id
 * @property IntakeSuggestionKind $kind
 * @property array<string, mixed> $payload
 * @property string $schema_version
 * @property string $confidence
 * @property string $reason
 * @property IntakeSuggestionStatus $status
 * @property int|null $created_task_id
 * @property int|null $created_resource_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read IntakeItem $item
 * @property-read Task|null $createdTask
 * @property-read resource|null $createdResource
 */
final class IntakeSuggestion extends Model
{
    /** @use HasFactory<IntakeSuggestionFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'public_id',
        'intake_item_id',
        'kind',
        'payload',
        'schema_version',
        'confidence',
        'reason',
        'status',
        'created_task_id',
        'created_resource_id',
    ];

    protected $hidden = [
        'id',
        'intake_item_id',
        'created_task_id',
        'created_resource_id',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<IntakeItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(IntakeItem::class, 'intake_item_id');
    }

    /** @return BelongsTo<Task, $this> */
    public function createdTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'created_task_id');
    }

    /** @return BelongsTo<resource, $this> */
    public function createdResource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'created_resource_id');
    }

    protected static function newFactory(): IntakeSuggestionFactory
    {
        return IntakeSuggestionFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => IntakeSuggestionKind::class,
            'payload' => 'array',
            'status' => IntakeSuggestionStatus::class,
        ];
    }
}
