<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Guidance\Models\UserWorkflowPreference;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserWorkflowPreference> */
final class UserWorkflowPreferenceFactory extends Factory
{
    protected $model = UserWorkflowPreference::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workflow_recipe_id' => WorkflowRecipe::factory()->published(),
            'state' => GuidancePreferenceState::Saved->value,
        ];
    }

    public function saved(): static
    {
        return $this->state(fn (): array => ['state' => GuidancePreferenceState::Saved->value]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (): array => ['state' => GuidancePreferenceState::Dismissed->value]);
    }
}
