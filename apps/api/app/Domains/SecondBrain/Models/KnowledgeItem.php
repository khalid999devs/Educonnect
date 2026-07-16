<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Models;

use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Database\Factories\KnowledgeItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class KnowledgeItem extends Model
{
    /** @use HasFactory<KnowledgeItemFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = ['id', 'public_id', 'user_id', 'version', 'search_vector'];

    protected $hidden = ['id', 'user_id', 'resource_id', 'search_vector'];

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

    /** @return BelongsTo<\App\Domains\Resources\Models\Resource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /** @return HasMany<KnowledgeNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(KnowledgeNote::class);
    }

    /** @return BelongsToMany<KnowledgeTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeTag::class,
            'knowledge_item_tags',
            'knowledge_item_id',
            'knowledge_tag_id',
        );
    }

    /** @return BelongsToMany<Collection, $this> */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(
            Collection::class,
            'collection_knowledge_items',
            'knowledge_item_id',
            'collection_id',
        );
    }

    /** @return HasMany<KnowledgeLink, $this> */
    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(KnowledgeLink::class, 'from_item_id');
    }

    /** @return HasMany<KnowledgeLink, $this> */
    public function incomingLinks(): HasMany
    {
        return $this->hasMany(KnowledgeLink::class, 'to_item_id');
    }

    /** @return BelongsToMany<ResearchTopic, $this> */
    public function researchTopics(): BelongsToMany
    {
        return $this->belongsToMany(
            ResearchTopic::class,
            'research_topic_sources',
            'knowledge_item_id',
            'research_topic_id',
        )->withPivot('reading_status');
    }

    protected static function newFactory(): KnowledgeItemFactory
    {
        return KnowledgeItemFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_year' => 'integer',
            'cursor_created_at_asc' => 'immutable_datetime',
            'cursor_created_at_desc' => 'immutable_datetime',
            'cursor_updated_at_asc' => 'immutable_datetime',
            'cursor_updated_at_desc' => 'immutable_datetime',
        ];
    }
}
