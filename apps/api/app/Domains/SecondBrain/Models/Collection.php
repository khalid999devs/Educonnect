<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Models;

use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Database\Factories\CollectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
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
    public function knowledgeItems(): BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeItem::class,
            'collection_knowledge_items',
            'collection_id',
            'knowledge_item_id',
        );
    }

    protected static function newFactory(): CollectionFactory
    {
        return CollectionFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'cursor_created_at_asc' => 'immutable_datetime',
            'cursor_created_at_desc' => 'immutable_datetime',
            'cursor_updated_at_asc' => 'immutable_datetime',
            'cursor_updated_at_desc' => 'immutable_datetime',
        ];
    }
}
