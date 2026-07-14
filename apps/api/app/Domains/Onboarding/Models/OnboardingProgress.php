<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Models;

use App\Domains\Onboarding\Enums\OnboardingStep;
use App\Domains\Onboarding\Enums\OnboardingStepState;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $version
 * @property ?CarbonInterface $completed_at
 */
final class OnboardingProgress extends Model
{
    use StoresUtcDateTimes;

    protected $table = 'onboarding_progress';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = ['*'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stateFor(OnboardingStep $step): OnboardingStepState
    {
        return OnboardingStepState::from((string) $this->getAttribute($step->stateColumn()));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
