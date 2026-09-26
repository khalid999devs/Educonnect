<?php

declare(strict_types=1);

namespace App\Domains\Templates\Models;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\UserTemplatePreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $template_id
 * @property GuidancePreferenceState $state
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read Template $template
 */
final class UserTemplatePreference extends Model
{
    /** @use HasFactory<UserTemplatePreferenceFactory> */
    use HasFactory, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'user_id',
        'template_id',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'template_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Template, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    protected static function newFactory(): UserTemplatePreferenceFactory
    {
        return UserTemplatePreferenceFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => GuidancePreferenceState::class,
        ];
    }
}
