<?php

declare(strict_types=1);

namespace App\Domains\Study\Models;

use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Study\Enums\StudyArtifactKind;
use App\Domains\Study\Enums\StudyArtifactStatus;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\StudyArtifactFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One generated study artifact: a summary, a topic-by-topic explanation, a
 * quick-learn walkthrough, or an exam-question set for one knowledge item.
 *
 * The row IS the cache and the dedupe key - `UNIQUE(knowledge_item_id, kind)` -
 * so a second request for the same material returns the same artifact rather
 * than re-billing a provider.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $knowledge_item_id
 * @property StudyArtifactKind $kind
 * @property StudyArtifactStatus $status
 * @property array<string, mixed>|null $payload
 * @property string $schema_version
 * @property string|null $provider
 * @property string|null $model
 * @property int|null $latency_ms
 * @property string|null $failure_reason
 * @property int $version
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read KnowledgeItem $knowledgeItem
 */
final class StudyArtifact extends Model
{
    /** @use HasFactory<StudyArtifactFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = ['id', 'public_id', 'user_id', 'knowledge_item_id', 'version'];

    protected $hidden = ['id', 'user_id', 'knowledge_item_id'];

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

    /** @return BelongsTo<KnowledgeItem, $this> */
    public function knowledgeItem(): BelongsTo
    {
        return $this->belongsTo(KnowledgeItem::class);
    }

    protected static function newFactory(): StudyArtifactFactory
    {
        return StudyArtifactFactory::new();
    }

    /**
     * The cursor alias columns are cast deliberately: without them the raw
     * timestamptz reaches the cursor as "Y-m-d H:i:s+00" and the cursor
     * validator rejects the next page with a 422.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => StudyArtifactKind::class,
            'status' => StudyArtifactStatus::class,
            'payload' => 'array',
            'latency_ms' => 'integer',
            'version' => 'integer',
            'cursor_created_at_asc' => 'immutable_datetime',
            'cursor_created_at_desc' => 'immutable_datetime',
            'cursor_updated_at_asc' => 'immutable_datetime',
            'cursor_updated_at_desc' => 'immutable_datetime',
        ];
    }
}
