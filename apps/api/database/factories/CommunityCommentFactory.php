<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Community\Models\CommunityComment;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommunityComment> */
final class CommunityCommentFactory extends Factory
{
    protected $model = CommunityComment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'post_id' => CommunityPost::factory(),
            'author_id' => User::factory(),
            'body' => 'Thanks for sharing this.',
            'moderation_state' => 'visible',
            'version' => 1,
        ];
    }
}
