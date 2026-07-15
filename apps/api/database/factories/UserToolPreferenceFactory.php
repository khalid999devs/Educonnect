<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Tools\Enums\ToolPreferenceState;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\UserToolPreference;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserToolPreference> */
final class UserToolPreferenceFactory extends Factory
{
    protected $model = UserToolPreference::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tool_id' => Tool::factory()->published(),
            'state' => ToolPreferenceState::Saved->value,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->getKey()]);
    }

    public function forTool(Tool $tool): static
    {
        return $this->state(fn (): array => ['tool_id' => $tool->getKey()]);
    }

    public function saved(): static
    {
        return $this->state(fn (): array => ['state' => ToolPreferenceState::Saved->value]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (): array => ['state' => ToolPreferenceState::Dismissed->value]);
    }
}
