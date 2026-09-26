<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Domains\Community\Models\Community;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CommunityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_report_requires_exactly_one_subject(): void
    {
        $community = Community::factory()->create();
        $reporter = User::factory()->create();

        // Neither post nor comment set.
        $this->assertQueryRejected('23514', static fn () => DB::table('content_reports')->insert([
            'public_id' => '0123456789abcdefghjkmnpqrs',
            'reporter_id' => $reporter->getKey(),
            'community_id' => $community->getKey(),
            'post_id' => null,
            'comment_id' => null,
            'reason' => 'spam',
            'status' => 'open',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function test_unknown_enum_values_are_rejected(): void
    {
        $this->assertQueryRejected('23514', static fn () => DB::table('communities')->insert([
            'public_id' => '0123456789abcdefghjkmnpqrs',
            'slug' => 'bad-visibility',
            'name' => 'Bad',
            'summary' => 'Bad visibility value.',
            'visibility' => 'secret',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $community = Community::factory()->create();
        $author = User::factory()->create();
        $this->assertQueryRejected('23514', static fn () => DB::table('community_posts')->insert([
            'public_id' => '0123456789abcdefghjkmnpqrs',
            'community_id' => $community->getKey(),
            'author_id' => $author->getKey(),
            'body' => 'Bad moderation state.',
            'moderation_state' => 'shadow_banned',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function test_public_id_is_immutable(): void
    {
        $community = Community::factory()->create();

        $this->assertQueryRejected('23514', static fn () => DB::table('communities')
            ->where('id', $community->getKey())
            ->update(['public_id' => '0123456789abcdefghjkmnpqrs']));
    }

    public function test_membership_is_unique_per_user(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        CommunityMembership::factory()->create([
            'community_id' => $community->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->assertQueryRejected('23505', static fn () => DB::table('community_memberships')->insert([
            'public_id' => '0123456789abcdefghjkmnpqrs',
            'community_id' => $community->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function test_a_reporter_cannot_report_the_same_post_twice(): void
    {
        $community = Community::factory()->create();
        $author = User::factory()->create();
        $post = CommunityPost::factory()->create([
            'community_id' => $community->getKey(),
            'author_id' => $author->getKey(),
        ]);
        $reporter = User::factory()->create();
        DB::table('content_reports')->insert([
            'public_id' => '0123456789abcdefghjkmnpqra',
            'reporter_id' => $reporter->getKey(),
            'community_id' => $community->getKey(),
            'post_id' => $post->getKey(),
            'reason' => 'spam',
            'status' => 'open',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertQueryRejected('23505', static fn () => DB::table('content_reports')->insert([
            'public_id' => '0123456789abcdefghjkmnpqrb',
            'reporter_id' => $reporter->getKey(),
            'community_id' => $community->getKey(),
            'post_id' => $post->getKey(),
            'reason' => 'harassment',
            'status' => 'open',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function test_a_user_can_only_have_one_mentor_profile(): void
    {
        $user = User::factory()->create();
        MentorProfile::factory()->create(['user_id' => $user->getKey()]);

        $this->assertQueryRejected('23505', static fn () => DB::table('mentor_profiles')->insert([
            'public_id' => '0123456789abcdefghjkmnpqrs',
            'user_id' => $user->getKey(),
            'headline' => 'Second profile',
            'bio' => 'This should not be allowed.',
            'expertise' => '[]',
            'verification_state' => 'unverified',
            'is_accepting_requests' => true,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function assertQueryRejected(string $expectedSqlState, callable $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            self::assertSame($expectedSqlState, $exception->errorInfo[0] ?? null);

            return;
        }

        self::fail("Expected PostgreSQL to reject the statement with SQLSTATE {$expectedSqlState}.");
    }
}
