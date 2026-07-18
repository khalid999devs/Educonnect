<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommunityMembership> */
final class CommunityMembershipFactory extends Factory
{
    protected $model = CommunityMembership::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id' => User::factory(),
            'role' => 'member',
        ];
    }

    public function moderator(): static
    {
        return $this->state(fn (): array => ['role' => 'moderator']);
    }
}
