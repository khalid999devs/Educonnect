<?php

declare(strict_types=1);

namespace App\Domains\Tools\Models;

use App\Domains\Tools\Enums\ToolPreferenceState;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\UserToolPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $tool_id
 * @property ToolPreferenceState $state
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read Tool $tool
 */
final class UserToolPreference extends Model
{
    /** @use HasFactory<UserToolPreferenceFactory> */
    use HasFactory, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'user_id',
        'tool_id',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'tool_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Tool, $this> */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    protected static function newFactory(): UserToolPreferenceFactory
    {
        return UserToolPreferenceFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => ToolPreferenceState::class,
        ];
    }
}
