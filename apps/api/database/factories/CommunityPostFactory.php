<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommunityPost> */
final class CommunityPostFactory extends Factory
{
    protected $model = CommunityPost::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'author_id' => User::factory(),
            'title' => null,
            'body' => 'A helpful update shared with the community.',
            'shared_resource_id' => null,
            'moderation_state' => 'visible',
            'version' => 1,
        ];
    }

    public function hiddenByModerator(): static
    {
        return $this->state(fn (): array => ['moderation_state' => 'hidden_by_moderator']);
    }

    public function removedByAuthor(): static
    {
        return $this->state(fn (): array => ['moderation_state' => 'removed_by_author']);
    }
}
