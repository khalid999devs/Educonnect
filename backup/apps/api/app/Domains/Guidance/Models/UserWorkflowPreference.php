<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Models;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\UserWorkflowPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $workflow_recipe_id
 * @property GuidancePreferenceState $state
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read WorkflowRecipe $workflowRecipe
 */
final class UserWorkflowPreference extends Model
{
    /** @use HasFactory<UserWorkflowPreferenceFactory> */
    use HasFactory, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'user_id',
        'workflow_recipe_id',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'workflow_recipe_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<WorkflowRecipe, $this> */
    public function workflowRecipe(): BelongsTo
    {
        return $this->belongsTo(WorkflowRecipe::class);
    }

    protected static function newFactory(): UserWorkflowPreferenceFactory
    {
        return UserWorkflowPreferenceFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => GuidancePreferenceState::class,
        ];
    }
}
