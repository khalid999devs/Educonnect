<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Models;

use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\UserPromptCopyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $prompt_template_id
 * @property int $copy_count
 * @property CarbonImmutable $first_copied_at
 * @property CarbonImmutable $last_copied_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read PromptTemplate $promptTemplate
 */
final class UserPromptCopy extends Model
{
    /** @use HasFactory<UserPromptCopyFactory> */
    use HasFactory, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'user_id',
        'prompt_template_id',
        'copy_count',
        'first_copied_at',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'prompt_template_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PromptTemplate, $this> */
    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }

    protected static function newFactory(): UserPromptCopyFactory
    {
        return UserPromptCopyFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'copy_count' => 'integer',
            'first_copied_at' => 'immutable_datetime',
            'last_copied_at' => 'immutable_datetime',
        ];
    }
}
