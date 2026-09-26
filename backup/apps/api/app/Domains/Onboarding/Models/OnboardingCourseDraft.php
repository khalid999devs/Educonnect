<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Models;

use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OnboardingCourseDraft extends Model
{
    use StoresUtcDateTimes;

    protected $guarded = ['id', 'user_id', 'position'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
