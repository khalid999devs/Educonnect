<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityComment;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Models\ContentReport;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentReport> */
final class ContentReportFactory extends Factory
{
    protected $model = ContentReport::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'community_id' => Community::factory(),
            'post_id' => null,
            'comment_id' => null,
            'reason' => 'spam',
            'detail' => null,
            'status' => 'open',
            'resolution_note' => null,
            'handled_by_id' => null,
            'version' => 1,
        ];
    }

    public function forPost(CommunityPost $post): static
    {
        return $this->state(fn (): array => [
            'community_id' => $post->community_id,
            'post_id' => $post->getKey(),
            'comment_id' => null,
        ]);
    }

    public function forComment(CommunityComment $comment): static
    {
        $communityId = CommunityPost::query()->whereKey($comment->post_id)->value('community_id');

        return $this->state(fn (): array => [
            'community_id' => $communityId,
            'post_id' => null,
            'comment_id' => $comment->getKey(),
        ]);
    }
}
