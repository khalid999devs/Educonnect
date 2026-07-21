<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Domains\Users\Data\SessionSummary;
use App\Domains\Users\Models\User;
use App\Domains\Users\Queries\ListOwnSessions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the current-session flag directly against the query that owns it.
 *
 * The HTTP test cannot pin the request's session id: the suite runs the array
 * session driver, which mints a fresh id per request. ListOwnSessions takes the
 * id as an argument precisely so the comparison is testable here, deterministically.
 */
final class CurrentSessionFlagTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT_SESSION_ID = 'aaaaaaaaaacurrentaaaaaaaaaaaaaaaaaaaaaaa';

    private const OTHER_SESSION_ID = 'bbbbbbbbbbotherbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_exactly_the_matching_session_is_flagged_as_current(): void
    {
        $user = User::factory()->create();
        $this->seedSession(self::CURRENT_SESSION_ID, $user, 200);
        $this->seedSession(self::OTHER_SESSION_ID, $user, 100);

        $summaries = app(ListOwnSessions::class)->execute($user, self::CURRENT_SESSION_ID);

        self::assertCount(2, $summaries);
        self::assertTrue($summaries[0]->isCurrent, 'The most recent session is the supplied current one.');
        self::assertFalse($summaries[1]->isCurrent);
        self::assertSame(
            1,
            count(array_filter($summaries, static fn (SessionSummary $s): bool => $s->isCurrent)),
            'Exactly one session may ever be flagged current.',
        );
    }

    public function test_no_session_is_flagged_when_the_request_has_no_session(): void
    {
        $user = User::factory()->create();
        $this->seedSession(self::CURRENT_SESSION_ID, $user, 200);

        $summaries = app(ListOwnSessions::class)->execute($user, null);

        self::assertCount(1, $summaries);
        self::assertFalse($summaries[0]->isCurrent);
    }

    public function test_an_unknown_session_id_flags_nothing_and_never_throws(): void
    {
        $user = User::factory()->create();
        $this->seedSession(self::CURRENT_SESSION_ID, $user, 200);

        $summaries = app(ListOwnSessions::class)->execute($user, self::OTHER_SESSION_ID);

        self::assertCount(1, $summaries);
        self::assertFalse($summaries[0]->isCurrent);
    }

    public function test_the_raw_session_identifier_is_never_carried_on_the_summary(): void
    {
        $user = User::factory()->create();
        $this->seedSession(self::CURRENT_SESSION_ID, $user, 200);

        $summaries = app(ListOwnSessions::class)->execute($user, self::CURRENT_SESSION_ID);

        self::assertNotSame(self::CURRENT_SESSION_ID, $summaries[0]->id);
        self::assertStringNotContainsString(
            self::CURRENT_SESSION_ID,
            (string) json_encode($summaries[0]),
            'The bearer session id must never leave the server.',
        );
        self::assertSame(ListOwnSessions::digest(self::CURRENT_SESSION_ID), $summaries[0]->id);
    }

    private function seedSession(string $id, User $user, int $lastActivity): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'ip_address' => '203.0.113.10',
            'user_agent' => 'EduConnect PHPUnit',
            'payload' => 'test-session-payload',
            'last_activity' => $lastActivity,
        ]);
    }
}
