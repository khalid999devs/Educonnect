<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Community\Concerns\InteractsWithCommunity;
use Tests\TestCase;

final class CommunityFeedTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_member_posts_appear_in_the_feed_and_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $this->actingAs($user, 'web');

        $joined = $this->withHeaders($this->headers())
            ->postJson("/api/v1/communities/{$community->public_id}/membership")
            ->assertOk()
            ->assertJsonPath('data.is_member', true);
        self::assertSame('member', $joined->json('data.membership_role'));

        $created = $this->withHeaders($this->headers())
            ->postJson("/api/v1/communities/{$community->public_id}/posts", [
                'title' => 'Study group forming',
                'body' => 'Anyone want to revise algorithms together this week?',
            ])
            ->assertCreated()
            ->assertJsonPath('data.moderation_state', 'visible')
            ->assertJsonPath('data.comment_count', 0)
            ->assertJsonPath('data.author.is_verified_mentor', false);
        $postId = $created->json('data.id');
        self::assertIsString($postId);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/feed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $postId);

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/communities/{$community->public_id}/posts")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_non_member_cannot_post_or_comment(): void
    {
        $community = Community::factory()->create();
        $member = User::factory()->create();
        CommunityMembership::factory()->create([
            'community_id' => $community->getKey(),
            'user_id' => $member->getKey(),
        ]);
        $post = CommunityPost::factory()->create([
            'community_id' => $community->getKey(),
            'author_id' => $member->getKey(),
        ]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider, 'web');

        $denied = $this->withHeaders($this->headers())
            ->postJson("/api/v1/communities/{$community->public_id}/posts", [
                'body' => 'I never joined this community.',
            ]);
        $this->assertApiError($denied, 403, ApiErrorCode::AuthorizationDenied);

        $deniedComment = $this->withHeaders($this->headers())
            ->postJson("/api/v1/posts/{$post->public_id}/comments", [
                'body' => 'Commenting without joining.',
            ]);
        $this->assertApiError($deniedComment, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_author_edits_and_removes_own_post_with_optimistic_concurrency(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $this->actingAs($user, 'web');
        $this->withHeaders($this->headers())
            ->postJson("/api/v1/communities/{$community->public_id}/membership")
            ->assertOk();

        $post = $this->withHeaders($this->headers())
            ->postJson("/api/v1/communities/{$community->public_id}/posts", [
                'body' => 'Original body.',
            ])
            ->assertCreated();
        $postId = $post->json('data.id');

        $this->withHeaders($this->headers())
            ->patchJson("/api/v1/posts/{$postId}", [
                'body' => 'Updated body with more detail.',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $stale = $this->withHeaders($this->headers())
            ->patchJson("/api/v1/posts/{$postId}", [
                'body' => 'Conflicting edit.',
                'expected_version' => 1,
            ]);
        $this->assertApiError($stale, 409, ApiErrorCode::Conflict);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/posts/{$postId}", ['expected_version' => 2])
            ->assertNoContent();

        // Removed posts fall out of the feed.
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/feed')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_comment_count_reflects_visible_comments(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $this->actingAs($user, 'web');
        $this->withHeaders($this->headers())
            ->postJson("/api/v1/communities/{$community->public_id}/membership")
            ->assertOk();
        $post = $this->withHeaders($this->headers())
            ->postJson("/api/v1/communities/{$community->public_id}/posts", ['body' => 'A post to comment on.'])
            ->assertCreated();
        $postId = $post->json('data.id');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/posts/{$postId}/comments", ['body' => 'Great idea!'])
            ->assertCreated();

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/posts/{$postId}")
            ->assertOk()
            ->assertJsonPath('data.comment_count', 1);

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/posts/{$postId}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
