<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\UserPromptCopy;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserPromptCopy> */
final class UserPromptCopyFactory extends Factory
{
    protected $model = UserPromptCopy::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $copiedAt = now()->toImmutable();

        return [
            'user_id' => User::factory(),
            'prompt_template_id' => PromptTemplate::factory()->published(),
            'copy_count' => 1,
            'first_copied_at' => $copiedAt,
            'last_copied_at' => $copiedAt,
        ];
    }
}
