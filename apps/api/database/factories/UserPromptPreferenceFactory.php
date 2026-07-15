<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\UserPromptPreference;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserPromptPreference> */
final class UserPromptPreferenceFactory extends Factory
{
    protected $model = UserPromptPreference::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'prompt_template_id' => PromptTemplate::factory()->published(),
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
