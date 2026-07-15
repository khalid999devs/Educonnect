<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\UserTemplatePreference;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserTemplatePreference> */
final class UserTemplatePreferenceFactory extends Factory
{
    protected $model = UserTemplatePreference::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'template_id' => Template::factory()->published(),
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
