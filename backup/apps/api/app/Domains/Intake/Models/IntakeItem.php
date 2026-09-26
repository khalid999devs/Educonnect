<?php

declare(strict_types=1);

namespace App\Domains\Intake\Models;

use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Enums\IntakeSourceType;
use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\IntakeItemFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property IntakeSourceType $source_type
 * @property int|null $resource_id
 * @property string|null $url
 * @property string|null $context
 * @property IntakeState $state
 * @property IntakeFailureCode|null $failure_code
 * @property int $attempts
 * @property int $version
 * @property CarbonImmutable|null $queued_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read resource|null $resource
 * @property-read Collection<int, IntakeArtifact> $artifacts
 * @property-read Collection<int, IntakeEvent> $events
 * @property-read Collection<int, IntakeSuggestion> $suggestions
 * @property string|null $classification_provider
 * @property string|null $classification_model
 * @property string|null $classification_schema_version
 * @property int|null $classification_latency_ms
 */
final class IntakeItem extends Model
{
    /** @use HasFactory<IntakeItemFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'public_id',
        'user_id',
        'source_type',
        'resource_id',
        'url',
        'state',
        'failure_code',
        'attempts',
        'version',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'resource_id',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<resource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /** @return HasMany<IntakeArtifact, $this> */
    public function artifacts(): HasMany
    {
        return $this->hasMany(IntakeArtifact::class);
    }

    /** @return HasMany<IntakeEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(IntakeEvent::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<IntakeSuggestion, $this> */
    public function suggestions(): HasMany
    {
        return $this->hasMany(IntakeSuggestion::class)->orderBy('id');
    }

    protected static function newFactory(): IntakeItemFactory
    {
        return IntakeItemFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_type' => IntakeSourceType::class,
            'state' => IntakeState::class,
            'failure_code' => IntakeFailureCode::class,
            'attempts' => 'integer',
            'version' => 'integer',
            'queued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'intake_created_at_desc' => 'immutable_datetime',
        ];
    }
}
