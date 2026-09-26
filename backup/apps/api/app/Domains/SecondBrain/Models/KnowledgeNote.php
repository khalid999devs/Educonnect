<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Models;

use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Database\Factories\KnowledgeNoteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KnowledgeNote extends Model
{
    /** @use HasFactory<KnowledgeNoteFactory> */
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

    protected static function newFactory(): KnowledgeNoteFactory
    {
        return KnowledgeNoteFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
