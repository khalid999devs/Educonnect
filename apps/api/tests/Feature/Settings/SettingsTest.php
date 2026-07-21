<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Domains\Courses\Models\Course;
use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class SettingsTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

    // Laravel only accepts a 40-character alphanumeric session identifier.
    private const CURRENT_SESSION_ID = 'aaaaaaaaaacurrentaaaaaaaaaaaaaaaaaaaaaaa';

    private const OTHER_SESSION_ID = 'bbbbbbbbbbotherbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const FOREIGN_SESSION_ID = 'ccccccccccforeignccccccccccccccccccccccc';

    public function test_settings_read_returns_account_academic_profile_and_course_preferences(): void
    {
        $user = User::factory()->create(['name' => 'Nabila Rahman']);
        $this->actingAs($user, 'web');
        $this->completeMinimumOnboarding();

        Course::factory()->create(['user_id' => $user->getKey()]);
        Course::factory()->archived()->create(['user_id' => $user->getKey()]);
        Resource::factory()->create(['user_id' => $user->getKey(), 'course_id' => null]);

        $response = $this->getSettings()
            ->assertOk()
            ->assertJsonPath('data.account.id', $user->public_id)
            ->assertJsonPath('data.account.name', 'Nabila Rahman')
            ->assertJsonPath('data.account.email', $user->email)
            ->assertJsonPath('data.account.email_verified', true)
            ->assertJsonPath('data.profile.institution_name', 'KUET')
            ->assertJsonPath('data.profile.institution_country_code', 'BD')
            ->assertJsonPath('data.profile.department', null)
            ->assertJsonPath('data.course_preferences.total', 2)
            ->assertJsonPath('data.course_preferences.active', 1)
            ->assertJsonPath('data.course_preferences.archived', 1)
            ->assertJsonPath('data.course_preferences.unfiled_resources', 1)
            ->assertJsonPath('data.onboarding_completed', true)
            ->assertJsonMissingPath('data.account.password')
            ->assertJsonMissingPath('data.account.user_id');

        $this->assertSuccessRequestId($response);
    }

    public function test_profile_round_trips_and_keeps_the_onboarding_aggregate_consistent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $this->completeMinimumOnboarding();

        $written = $this->putProfile([
            'institution_name' => '  Khulna University of Engineering and Technology  ',
            'institution_country_code' => 'bd',
            'department' => 'Computer Science and Engineering',
            'degree' => 'BSc',
            'major' => 'Software Engineering',
            'year_label' => 'Third year',
            'term_label' => 'Even term',
        ])
            ->assertOk()
            ->assertJsonPath('data.profile.institution_name', 'Khulna University of Engineering and Technology')
            ->assertJsonPath('data.profile.institution_country_code', 'BD')
            ->assertJsonPath('data.profile.department', 'Computer Science and Engineering')
            ->assertJsonPath('data.profile.degree', 'BSc')
            ->assertJsonPath('data.profile.major', 'Software Engineering')
            ->assertJsonPath('data.profile.year_label', 'Third year')
            ->assertJsonPath('data.profile.term_label', 'Even term');
        $this->assertSuccessRequestId($written);

        $this->getSettings()
            ->assertOk()
            ->assertJsonPath('data.profile.degree', 'BSc')
            ->assertJsonPath('data.profile.term_label', 'Even term');

        // The onboarding aggregate trigger couples profile columns to step states.
        $this->assertDatabaseHas('onboarding_progress', [
            'user_id' => $user->getKey(),
            'institution_state' => 'completed',
            'program_state' => 'completed',
            'study_stage_state' => 'completed',
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->getKey(),
            'department' => 'Computer Science and Engineering',
        ]);

        $this->getOnboarding()
            ->assertOk()
            ->assertJsonPath('data.onboarding.status', 'completed')
            ->assertJsonPath('data.onboarding.can_complete', true)
            ->assertJsonPath('data.onboarding.profile.major', 'Software Engineering');
    }

    public function test_clearing_optional_profile_details_moves_their_steps_to_skipped(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $this->completeMinimumOnboarding();

        $this->putProfile([
            'institution_name' => 'KUET',
            'institution_country_code' => 'BD',
            'department' => 'Physics',
            'degree' => null,
            'major' => null,
            'year_label' => 'First year',
            'term_label' => null,
        ])->assertOk();

        $this->putProfile([
            'institution_name' => 'KUET',
            'institution_country_code' => 'BD',
            'department' => null,
            'degree' => null,
            'major' => null,
            'year_label' => null,
            'term_label' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.profile.department', null)
            ->assertJsonPath('data.profile.year_label', null);

        $this->assertDatabaseHas('onboarding_progress', [
            'user_id' => $user->getKey(),
            'institution_state' => 'completed',
            'program_state' => 'skipped',
            'study_stage_state' => 'skipped',
        ]);

        $this->getOnboarding()
            ->assertOk()
            ->assertJsonPath('data.onboarding.status', 'completed')
            ->assertJsonPath('data.onboarding.can_complete', true);
    }

    public function test_settings_cannot_remove_the_institution_from_a_completed_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $this->completeMinimumOnboarding();

        $rejected = $this->putProfile([
            'institution_name' => null,
            'institution_country_code' => null,
            'department' => null,
            'degree' => null,
            'major' => null,
            'year_label' => null,
            'term_label' => null,
        ]);
        $this->assertApiError($rejected, 422, ApiErrorCode::ValidationFailed);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->getKey(),
            'institution_name' => 'KUET',
        ]);
        $this->assertDatabaseHas('onboarding_progress', [
            'user_id' => $user->getKey(),
            'institution_state' => 'completed',
        ]);
    }

    public function test_the_onboarding_post_completion_guard_is_untouched_and_still_enforced(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $version = $this->completeMinimumOnboarding();

        $this->putProfile([
            'institution_name' => 'KUET',
            'institution_country_code' => 'BD',
            'department' => 'Mathematics',
            'degree' => null,
            'major' => null,
            'year_label' => null,
            'term_label' => null,
        ])->assertOk();

        $version = (int) $this->getOnboarding()->json('data.onboarding.version');

        // Emptying the starter seed after completion is still refused by
        // UpdateOnboardingStepAction. Settings never relaxes that path.
        $rejected = $this->withHeaders($this->mutationHeaders())
            ->putJson('/api/v1/onboarding/steps/goals', [
                'expected_version' => $version,
                'state' => 'skipped',
            ]);
        $this->assertApiError($rejected, 422, ApiErrorCode::ValidationFailed);

        $this->assertDatabaseHas('onboarding_intents', [
            'user_id' => $user->getKey(),
            'kind' => 'goal',
            'text' => 'Create a study plan',
        ]);
    }

    public function test_profile_rejects_unknown_fields_partial_payloads_and_unpaired_institutions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $this->completeMinimumOnboarding();

        $unknown = $this->withHeaders($this->mutationHeaders())
            ->putJson('/api/v1/settings/profile', [
                'institution_name' => 'KUET',
                'institution_country_code' => 'BD',
                'department' => null,
                'degree' => null,
                'major' => null,
                'year_label' => null,
                'term_label' => null,
                'timezone' => 'Asia/Dhaka',
            ]);
        $this->assertApiError($unknown, 422, ApiErrorCode::ValidationFailed);
        $unknown->assertJsonStructure(['error' => ['details' => ['fields' => ['timezone']]]]);

        $partial = $this->withHeaders($this->mutationHeaders())
            ->putJson('/api/v1/settings/profile', ['department' => 'Chemistry']);
        $this->assertApiError($partial, 422, ApiErrorCode::ValidationFailed);

        $unpaired = $this->putProfile([
            'institution_name' => 'KUET',
            'institution_country_code' => null,
            'department' => null,
            'degree' => null,
            'major' => null,
            'year_label' => null,
            'term_label' => null,
        ]);
        $this->assertApiError($unpaired, 422, ApiErrorCode::ValidationFailed);

        $markup = $this->putProfile([
            'institution_name' => 'KUET',
            'institution_country_code' => 'BD',
            'department' => '<script>alert(1)</script>',
            'degree' => null,
            'major' => null,
            'year_label' => null,
            'term_label' => null,
        ]);
        $this->assertApiError($markup, 422, ApiErrorCode::ValidationFailed);
    }

    public function test_account_name_updates_and_rejects_unsupported_fields(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $this->actingAs($user, 'web');

        $updated = $this->withHeaders($this->mutationHeaders())
            ->putJson('/api/v1/settings/account', ['name' => '  New Name  '])
            ->assertOk()
            ->assertJsonPath('data.account.name', 'New Name');
        $this->assertSuccessRequestId($updated);
        $this->assertSame('New Name', $user->refresh()->name);

        // Email, password, theme, timezone, and notification changes are all
        // out of scope until their schema lands.
        foreach ([
            ['name' => 'New Name', 'email' => 'someone.else@example.com'],
            ['name' => 'New Name', 'password' => 'Sup3rSecret!pass'],
            ['name' => 'New Name', 'timezone' => 'Asia/Dhaka'],
            ['name' => 'New Name', 'theme' => 'dark'],
        ] as $payload) {
            $rejected = $this->withHeaders($this->mutationHeaders())
                ->putJson('/api/v1/settings/account', $payload);
            $this->assertApiError($rejected, 422, ApiErrorCode::ValidationFailed);
        }

        $this->assertSame($user->email, $user->refresh()->email);

        $blank = $this->withHeaders($this->mutationHeaders())
            ->putJson('/api/v1/settings/account', ['name' => '   ']);
        $this->assertApiError($blank, 422, ApiErrorCode::ValidationFailed);
    }

    public function test_sessions_list_is_owner_scoped_ordered_and_never_returns_raw_identifiers(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->insertSession(self::CURRENT_SESSION_ID, $user, '203.0.113.10', 'EduConnect PHPUnit', 200);
        $this->insertSession(self::OTHER_SESSION_ID, $user, null, null, 100);
        $this->insertSession(self::FOREIGN_SESSION_ID, $stranger, '198.51.100.7', 'Foreign agent', 300);

        /* Every row reads is_current false, and that is the truth rather than a
           weakened assertion: the test suite runs the array session driver, so
           this request's session id is generated per request and matches none
           of the seeded rows. The flag itself is covered directly against
           ListOwnSessions, which is the seam that owns the comparison. */
        $response = $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/settings/sessions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.is_current', false)
            ->assertJsonPath('data.0.ip_address', '203.0.113.10')
            ->assertJsonPath('data.0.user_agent', 'EduConnect PHPUnit')
            ->assertJsonPath('data.1.is_current', false)
            ->assertJsonPath('data.1.ip_address', null);
        $this->assertSuccessRequestId($response);

        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString(self::CURRENT_SESSION_ID, $body);
        $this->assertStringNotContainsString(self::OTHER_SESSION_ID, $body);
        $this->assertStringNotContainsString(self::FOREIGN_SESSION_ID, $body);
        $this->assertStringNotContainsString('198.51.100.7', $body);
        $this->assertStringNotContainsString('Foreign agent', $body);

        $ids = $response->json('data.*.id');
        $this->assertIsArray($ids);
        $this->assertCount(2, array_unique($ids));
    }

    private function insertSession(
        string $id,
        User $user,
        ?string $ip,
        ?string $agent,
        int $activityOffset,
    ): void {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'ip_address' => $ip,
            'user_agent' => $agent,
            'payload' => 'test-session-payload',
            'last_activity' => now()->getTimestamp() - (300 - $activityOffset),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function putProfile(array $payload): TestResponse
    {
        return $this->withHeaders($this->mutationHeaders())
            ->putJson('/api/v1/settings/profile', $payload);
    }

    private function getSettings(): TestResponse
    {
        return $this->withHeaders($this->readHeaders())->getJson('/api/v1/settings');
    }

    private function getOnboarding(): TestResponse
    {
        return $this->withHeaders($this->readHeaders())->getJson('/api/v1/onboarding');
    }

    private function completeMinimumOnboarding(): int
    {
        $version = (int) $this->updateStep('institution', 0, 'completed', [
            'institution_name' => 'KUET',
            'institution_country_code' => 'BD',
        ])->json('data.onboarding.version');

        foreach (['program', 'study_stage', 'courses'] as $step) {
            $version = (int) $this->updateStep($step, $version, 'skipped')->json('data.onboarding.version');
        }

        $version = (int) $this->updateStep('goals', $version, 'completed', [
            'goals' => ['Create a study plan'],
            'problems' => [],
        ])->json('data.onboarding.version');
        $version = (int) $this->updateStep('first_source', $version, 'skipped')->json('data.onboarding.version');

        return (int) $this->withHeaders($this->mutationHeaders())
            ->putJson('/api/v1/onboarding/completion', ['expected_version' => $version])
            ->assertOk()
            ->json('data.onboarding.version');
    }

    /** @param array<string, mixed>|null $data */
    private function updateStep(string $step, int $expectedVersion, string $state, ?array $data = null): TestResponse
    {
        $payload = ['expected_version' => $expectedVersion, 'state' => $state];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/onboarding/steps/{$step}", $payload)
            ->assertOk();
    }

    /** @return array<string, string> */
    private function mutationHeaders(): array
    {
        return [
            ...$this->readHeaders(),
            'X-XSRF-TOKEN' => 'settings-test-token',
        ];
    }

    /** @return array<string, string> */
    private function readHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }
}
