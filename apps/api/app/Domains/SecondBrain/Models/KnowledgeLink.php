<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Models;

use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KnowledgeLink extends Model
{
    use HasUlids, StoresUtcDateTimes;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'public_id', 'user_id'];

    protected $hidden = ['id', 'user_id', 'from_item_id', 'to_item_id'];

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
    public function fromItem(): BelongsTo
    {
        return $this->belongsTo(KnowledgeItem::class, 'from_item_id');
    }

    /** @return BelongsTo<KnowledgeItem, $this> */
    public function toItem(): BelongsTo
    {
        return $this->belongsTo(KnowledgeItem::class, 'to_item_id');
    }
}
