<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Community\Concerns\InteractsWithCommunity;
use Tests\TestCase;

final class CommunityMemberTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_member_sees_the_people_in_the_community(): void
    {
        $community = Community::factory()->create();
        $viewer = User::factory()->create(['name' => 'Viewer Vega']);
        $mentor = User::factory()->create(['name' => 'Mentor Mira']);

        CommunityMembership::factory()->create([
            'community_id' => $community->getKey(),
            'user_id' => $viewer->getKey(),
        ]);
        CommunityMembership::factory()->moderator()->create([
            'community_id' => $community->getKey(),
            'user_id' => $mentor->getKey(),
        ]);
        MentorProfile::factory()->verified()->create(['user_id' => $mentor->getKey()]);

        $this->actingAs($viewer, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/v1/communities/{$community->public_id}/members")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        /** @var array<int, array<string, mixed>> $people */
        $people = $response->json('data');
        $byName = [];

        foreach ($people as $person) {
            self::assertIsString($person['name']);
            $byName[$person['name']] = $person;
        }

        self::assertSame('moderator', $byName['Mentor Mira']['role']);
        self::assertTrue($byName['Mentor Mira']['is_verified_mentor']);
        self::assertSame('member', $byName['Viewer Vega']['role']);
        self::assertFalse($byName['Viewer Vega']['is_verified_mentor']);
        self::assertIsString($byName['Viewer Vega']['joined_at']);
        self::assertSame(2, $response->json('meta.summary.total'));
    }

    public function test_non_member_cannot_list_the_people_in_a_community(): void
    {
        $community = Community::factory()->create();
        $member = User::factory()->create();
        CommunityMembership::factory()->create([
            'community_id' => $community->getKey(),
            'user_id' => $member->getKey(),
        ]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider, 'web');

        $denied = $this->withHeaders($this->headers())
            ->getJson("/api/v1/communities/{$community->public_id}/members");

        $this->assertApiError($denied, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_guest_cannot_list_the_people_in_a_community(): void
    {
        $community = Community::factory()->create();

        $denied = $this->withHeaders($this->headers())
            ->getJson("/api/v1/communities/{$community->public_id}/members");

        $this->assertApiError($denied, 401, ApiErrorCode::AuthenticationRequired);
    }

    public function test_member_payload_never_exposes_an_email_or_an_internal_id(): void
    {
        $community = Community::factory()->create();
        $viewer = User::factory()->create();
        $other = User::factory()->create([
            'name' => 'Other Otto',
            'email' => 'leaky.person@example.com',
        ]);

        CommunityMembership::factory()->create([
            'community_id' => $community->getKey(),
            'user_id' => $viewer->getKey(),
        ]);
        $membership = CommunityMembership::factory()->create([
            'community_id' => $community->getKey(),
            'user_id' => $other->getKey(),
        ]);

        $this->actingAs($viewer, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/v1/communities/{$community->public_id}/members")
            ->assertOk();

        $body = $response->getContent();
        self::assertIsString($body);
        self::assertStringNotContainsString('leaky.person@example.com', $body);
        self::assertStringNotContainsString('@example.com', $body);
        self::assertStringNotContainsString('email', $body);

        /** @var array<int, array<string, mixed>> $people */
        $people = $response->json('data');

        foreach ($people as $person) {
            self::assertSame(
                ['id', 'name', 'role', 'joined_at', 'is_verified_mentor'],
                array_keys($person),
            );
            self::assertArrayNotHasKey('email', $person);
            self::assertArrayNotHasKey('user_id', $person);
            self::assertNotSame((string) $other->getKey(), (string) $person['id']);
            self::assertNotSame((string) $other->public_id, (string) $person['id']);
        }

        $identifiers = array_map(static fn (array $person): mixed => $person['id'], $people);
        self::assertContains($membership->public_id, $identifiers);
    }

    public function test_people_list_paginates_and_rejects_a_tampered_cursor(): void
    {
        $community = Community::factory()->create();
        $viewer = User::factory()->create();
        CommunityMembership::factory()->create([
            'community_id' => $community->getKey(),
            'user_id' => $viewer->getKey(),
        ]);

        foreach (range(1, 4) as $index) {
            CommunityMembership::factory()->create([
                'community_id' => $community->getKey(),
                'user_id' => User::factory()->create(['name' => "Person {$index}"])->getKey(),
            ]);
        }

        $this->actingAs($viewer, 'web');

        $firstPage = $this->withHeaders($this->headers())
            ->getJson("/api/v1/communities/{$community->public_id}/members?per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $cursor = $firstPage->json('meta.pagination.next_cursor');
        self::assertIsString($cursor);

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/communities/{$community->public_id}/members?per_page=2&cursor={$cursor}")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $tampered = $this->withHeaders($this->headers())
            ->getJson("/api/v1/communities/{$community->public_id}/members?cursor=not-a-real-cursor");
        $this->assertApiError($tampered, 422, ApiErrorCode::ValidationFailed);

        $forged = base64_encode((string) json_encode([
            'cursor_created_at_desc' => 'tomorrow',
            'public_id' => 'not-a-ulid',
            '_pointsToNextItems' => true,
        ]));
        $forgedResponse = $this->withHeaders($this->headers())
            ->getJson("/api/v1/communities/{$community->public_id}/members?cursor={$forged}");
        $this->assertApiError($forgedResponse, 422, ApiErrorCode::ValidationFailed);

        $unknownField = $this->withHeaders($this->headers())
            ->getJson("/api/v1/communities/{$community->public_id}/members?sort=-name");
        $this->assertApiError($unknownField, 422, ApiErrorCode::ValidationFailed);
    }

    public function test_unknown_community_is_not_found(): void
    {
        $viewer = User::factory()->create();
        $this->actingAs($viewer, 'web');

        $missing = $this->withHeaders($this->headers())
            ->getJson('/api/v1/communities/01hzzzzzzzzzzzzzzzzzzzzzzz/members');

        $this->assertApiError($missing, 404, ApiErrorCode::ResourceNotFound);
    }
}
