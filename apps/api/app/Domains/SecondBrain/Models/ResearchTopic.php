<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Models;

use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Database\Factories\ResearchTopicFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class ResearchTopic extends Model
{
    /** @use HasFactory<ResearchTopicFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = ['id', 'public_id', 'user_id', 'version'];

    protected $hidden = ['id', 'user_id'];

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

    /** @return BelongsToMany<KnowledgeItem, $this> */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeItem::class,
            'research_topic_sources',
            'research_topic_id',
            'knowledge_item_id',
        )->withPivot(['reading_status', 'created_at', 'updated_at']);
    }

    protected static function newFactory(): ResearchTopicFactory
    {
        return ResearchTopicFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'keywords' => 'array',
            'cursor_created_at_asc' => 'immutable_datetime',
            'cursor_created_at_desc' => 'immutable_datetime',
            'cursor_updated_at_asc' => 'immutable_datetime',
            'cursor_updated_at_desc' => 'immutable_datetime',
        ];
    }
}
