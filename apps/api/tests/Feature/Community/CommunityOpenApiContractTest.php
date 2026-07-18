<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityComment;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Models\ContentReport;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Feature\Community\Concerns\InteractsWithCommunity;
use Tests\TestCase;

final class CommunityOpenApiContractTest extends TestCase
{
    use InteractsWithCommunity;
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_community_member_operations_match_the_live_openapi_contract(): void
    {
        $community = Community::factory()->create();

        $otherAuthor = User::factory()->create();
        CommunityMembership::factory()->create([
            'community_id' => $community->getKey(),
            'user_id' => $otherAuthor->getKey(),
        ]);
        $otherPost = CommunityPost::factory()->create([
            'community_id' => $community->getKey(),
            'author_id' => $otherAuthor->getKey(),
        ]);
        $otherComment = CommunityComment::factory()->create([
            'post_id' => $otherPost->getKey(),
            'author_id' => $otherAuthor->getKey(),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // Membership.
        $this->withHeaders($this->headers())
            ->postJson("/api/v1/communities/{$community->public_id}/membership")
            ->assertOk()
            ->assertJsonPath('data.is_member', true);

        // Directory reads.
        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/communities?search=Community&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/communities/{$community->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $community->public_id);

        // Publish and edit a post.
        $postId = $this->withHeaders($this->headers())
            ->postJson("/api/v1/communities/{$community->public_id}/posts", [
                'title' => 'Contract study group',
                'body' => 'Revising graph algorithms this weekend.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.moderation_state', 'visible')
            ->assertJsonPath('data.is_mine', true)
            ->json('data.id');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/feed?per_page=10')
            ->assertOk();

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/communities/{$community->public_id}/posts?per_page=10")
            ->assertOk();

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/posts/{$postId}")
            ->assertOk()
            ->assertJsonPath('data.id', $postId);

        $this->withHeaders($this->headers())
            ->patchJson("/api/v1/posts/{$postId}", [
                'body' => 'Revising graph and flow algorithms this weekend.',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        // Comment on the post.
        $commentId = $this->withHeaders($this->headers())
            ->postJson("/api/v1/posts/{$postId}/comments", ['body' => 'Count me in!'])
            ->assertCreated()
            ->assertJsonPath('data.is_mine', true)
            ->json('data.id');

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/posts/{$postId}/comments?per_page=10")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Report other members' content.
        $this->withHeaders($this->headers())
            ->postJson("/api/v1/posts/{$otherPost->public_id}/reports", [
                'reason' => 'spam',
                'detail' => 'Looks like an advertisement.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.subject.type', 'post');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/comments/{$otherComment->public_id}/reports", ['reason' => 'off_topic'])
            ->assertCreated()
            ->assertJsonPath('data.subject.type', 'comment');

        // Destructive paths.
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/comments/{$commentId}", ['expected_version' => 1])
            ->assertNoContent();

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/posts/{$postId}", ['expected_version' => 2])
            ->assertNoContent();

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/communities/{$community->public_id}/membership")
            ->assertOk()
            ->assertJsonPath('data.is_member', false);

        // Invalid input still produces the concealed error contract.
        $this->withoutRequestValidation()
            ->withHeaders($this->headers())
            ->postJson("/api/v1/communities/{$community->public_id}/posts", ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_moderator_operations_match_the_live_openapi_contract(): void
    {
        $community = Community::factory()->create();
        $author = User::factory()->create();
        CommunityMembership::factory()->create([
            'community_id' => $community->getKey(),
            'user_id' => $author->getKey(),
        ]);
        $post = CommunityPost::factory()->create([
            'community_id' => $community->getKey(),
            'author_id' => $author->getKey(),
        ]);
        $reporter = User::factory()->create();
        $report = ContentReport::factory()->forPost($post)->create(['reporter_id' => $reporter->getKey()]);

        $moderator = User::factory()->withRole(RoleKey::Moderator)->create();
        CommunityMembership::factory()->moderator()->create([
            'community_id' => $community->getKey(),
            'user_id' => $moderator->getKey(),
        ]);
        $this->actingAs($moderator, 'web');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/moderation/reports?status=open&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $report->public_id);

        $this->withHeaders($this->headers())
            ->patchJson("/api/v1/moderation/reports/{$report->public_id}/resolution", [
                'resolution' => 'actioned',
                'hide_content' => true,
                'note' => 'Removed for spam.',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'actioned');
    }

    /** @return array<string, string> */
    private function readHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'community-contract-session');

        return $authenticatedRequest;
    }
}
