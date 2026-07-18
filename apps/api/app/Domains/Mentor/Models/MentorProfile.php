<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Models;

use App\Domains\Mentor\Enums\MentorVerificationState;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Database\Factories\MentorProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $headline
 * @property string $bio
 * @property list<string> $expertise
 * @property string|null $availability_note
 * @property MentorVerificationState $verification_state
 * @property bool $is_accepting_requests
 * @property int $version
 */
final class MentorProfile extends Model
{
    /** @use HasFactory<MentorProfileFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = ['id', 'public_id', 'user_id', 'verification_state', 'version'];

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

    /** @return HasMany<MentorRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(MentorRequest::class);
    }

    protected static function newFactory(): MentorProfileFactory
    {
        return MentorProfileFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expertise' => 'array',
            'verification_state' => MentorVerificationState::class,
            'is_accepting_requests' => 'boolean',
            'version' => 'integer',
            'cursor_created_at_desc' => 'immutable_datetime',
        ];
    }
}
