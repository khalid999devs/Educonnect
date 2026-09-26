<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Models;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\UserPromptPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $prompt_template_id
 * @property GuidancePreferenceState $state
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read PromptTemplate $promptTemplate
 */
final class UserPromptPreference extends Model
{
    /** @use HasFactory<UserPromptPreferenceFactory> */
    use HasFactory, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'user_id',
        'prompt_template_id',
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

    protected static function newFactory(): UserPromptPreferenceFactory
    {
        return UserPromptPreferenceFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => GuidancePreferenceState::class,
        ];
    }
}
