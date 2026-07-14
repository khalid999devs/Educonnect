<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Domains\Onboarding\Models\OnboardingProgress;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class OnboardingLifecycleTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

    public function test_status_read_is_side_effect_free_and_minimum_onboarding_can_resume_and_complete(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $initial = $this->getOnboarding()
            ->assertOk()
            ->assertJsonPath('data.onboarding.status', 'not_started')
            ->assertJsonPath('data.onboarding.current_step', 'institution')
            ->assertJsonPath('data.onboarding.version', 0);
        $this->assertSuccessRequestId($initial);
        $this->assertDatabaseCount('onboarding_progress', 0);
        $this->assertDatabaseCount('user_profiles', 0);

        $version = $this->updateStep('institution', 0, 'completed', [
            'institution_name' => 'KUET',
            'institution_country_code' => 'bd',
        ])->assertJsonPath('data.onboarding.current_step', 'program')
            ->json('data.onboarding.version');

        foreach (['program', 'study_stage', 'courses'] as $step) {
            $version = $this->updateStep($step, $version, 'skipped')
                ->json('data.onboarding.version');
        }

        $version = $this->updateStep('goals', $version, 'completed', [
            'goals' => ['Build a consistent study plan'],
            'problems' => [],
        ])->json('data.onboarding.version');
        $version = $this->updateStep('first_source', $version, 'skipped')
            ->assertJsonPath('data.onboarding.can_complete', true)
            ->json('data.onboarding.version');

        $this->assertSame(6, $version);

        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');

        $resumed = $this->getOnboarding()
            ->assertOk()
            ->assertJsonPath('data.onboarding.version', 6)
            ->assertJsonPath('data.onboarding.profile.institution_name', 'KUET')
            ->assertJsonPath('data.onboarding.steps.program', 'skipped')
            ->assertJsonPath('data.onboarding.goals.0', 'Build a consistent study plan');
        $this->assertSuccessRequestId($resumed);

        $completed = $this->complete(6)
            ->assertOk()
            ->assertJsonPath('data.onboarding.status', 'completed')
            ->assertJsonPath('data.onboarding.version', 7)
            ->assertJsonPath('data.onboarding.starter_context.program.department', null)
            ->assertJsonPath('data.onboarding.starter_context.goals.0', 'Build a consistent study plan');
        $this->assertSuccessRequestId($completed);
    }

    public function test_exact_retry_is_a_noop_and_skipping_purges_optional_private_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $payload = [
            'goals' => ['Finish the semester well'],
            'problems' => ['Scattered notes'],
        ];
        $first = $this->updateStep('goals', 0, 'completed', $payload)
            ->assertJsonPath('data.onboarding.version', 1);
        $this->assertSuccessRequestId($first);

        $updatedAt = DB::table('onboarding_progress')->where('user_id', $user->getKey())->value('updated_at');
        $this->travel(10)->seconds();

        $retry = $this->updateStep('goals', 0, 'completed', $payload)
            ->assertJsonPath('data.onboarding.version', 1);
        $this->assertSuccessRequestId($retry);
        $this->assertSame(
            $updatedAt,
            DB::table('onboarding_progress')->where('user_id', $user->getKey())->value('updated_at'),
        );
        $this->assertDatabaseCount('onboarding_intents', 2);

        $skipped = $this->updateStep('goals', 1, 'skipped')
            ->assertJsonPath('data.onboarding.version', 2)
            ->assertJsonPath('data.onboarding.goals', [])
            ->assertJsonPath('data.onboarding.problems', []);
        $this->assertSuccessRequestId($skipped);
        $this->assertDatabaseCount('onboarding_intents', 0);

        $this->updateStep('goals', 1, 'skipped')
            ->assertJsonPath('data.onboarding.version', 2);
    }

    public function test_completed_profile_supports_progressive_updates_without_losing_completion_invariants(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $version = $this->completeMinimumOnboarding();

        $completedAt = OnboardingProgress::query()->findOrFail($user->getKey())->completed_at?->toISOString();
        $this->assertIsString($completedAt);

        $rejected = $this->updateStep('goals', $version, 'skipped', expectedStatus: 422);
        $this->assertApiError($rejected, 422, ApiErrorCode::ValidationFailed);
        $this->assertDatabaseHas('onboarding_intents', [
            'user_id' => $user->getKey(),
            'kind' => 'goal',
            'text' => 'Create a study plan',
        ]);
        $this->assertDatabaseHas('onboarding_progress', [
            'user_id' => $user->getKey(),
            'version' => $version,
            'goals_state' => 'completed',
        ]);
        $this->assertSame(
            $completedAt,
            OnboardingProgress::query()->findOrFail($user->getKey())->completed_at?->toISOString(),
        );

        $updated = $this->updateStep('program', $version, 'completed', [
            'department' => 'Computer Science and Engineering',
            'degree' => null,
            'major' => null,
        ])->assertJsonPath('data.onboarding.version', $version + 1)
            ->assertJsonPath(
                'data.onboarding.starter_context.program.department',
                'Computer Science and Engineering',
            )
            ->assertJsonPath('data.onboarding.completed_at', $completedAt);
        $this->assertSuccessRequestId($updated);

        $this->complete(0)
            ->assertOk()
            ->assertJsonPath('data.onboarding.version', $version + 1)
            ->assertJsonPath('data.onboarding.completed_at', $completedAt);
    }

    public function test_first_source_is_stored_as_a_link_draft_without_outbound_network_activity(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->updateStep('first_source', 0, 'completed', [
            'url' => 'https://example.edu/syllabus?term=fall',
            'title' => 'Course syllabus',
        ])->assertJsonPath('data.onboarding.first_source_draft.url', 'https://example.edu/syllabus?term=fall')
            ->assertJsonPath('data.onboarding.starter_context', null);
        $this->assertSuccessRequestId($response);

        Http::assertNothingSent();
        $this->assertDatabaseHas('onboarding_progress', [
            'user_id' => $user->getKey(),
            'first_source_state' => 'completed',
            'first_source_url' => 'https://example.edu/syllabus?term=fall',
        ]);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_all_terminal_skips_point_back_to_an_actionable_seed_step(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $version = $this->updateStep('institution', 0, 'completed', [
            'institution_name' => 'KUET',
            'institution_country_code' => 'BD',
        ])->json('data.onboarding.version');

        foreach (['program', 'study_stage', 'courses', 'goals', 'first_source'] as $step) {
            $version = $this->updateStep($step, $version, 'skipped')->json('data.onboarding.version');
        }

        $this->getOnboarding()
            ->assertOk()
            ->assertJsonPath('data.onboarding.version', 6)
            ->assertJsonPath('data.onboarding.status', 'in_progress')
            ->assertJsonPath('data.onboarding.can_complete', false)
            ->assertJsonPath('data.onboarding.current_step', 'courses');
    }

    private function completeMinimumOnboarding(): int
    {
        $version = $this->updateStep('institution', 0, 'completed', [
            'institution_name' => 'KUET',
            'institution_country_code' => 'BD',
        ])->json('data.onboarding.version');

        foreach (['program', 'study_stage', 'courses'] as $step) {
            $version = $this->updateStep($step, $version, 'skipped')->json('data.onboarding.version');
        }

        $version = $this->updateStep('goals', $version, 'completed', [
            'goals' => ['Create a study plan'],
            'problems' => [],
        ])->json('data.onboarding.version');
        $version = $this->updateStep('first_source', $version, 'skipped')->json('data.onboarding.version');

        return (int) $this->complete($version)
            ->assertOk()
            ->json('data.onboarding.version');
    }

    private function getOnboarding(): TestResponse
    {
        return $this->withHeaders($this->statefulHeaders())->getJson('/api/v1/onboarding');
    }

    /** @param  array<string, mixed>|null  $data */
    private function updateStep(
        string $step,
        int $expectedVersion,
        string $state,
        ?array $data = null,
        int $expectedStatus = 200,
    ): TestResponse {
        $payload = [
            'expected_version' => $expectedVersion,
            'state' => $state,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return $this->withHeaders($this->statefulMutationHeaders())
            ->putJson("/api/v1/onboarding/steps/{$step}", $payload)
            ->assertStatus($expectedStatus);
    }

    private function complete(int $expectedVersion): TestResponse
    {
        return $this->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/completion', ['expected_version' => $expectedVersion]);
    }

    /** @return array<string, string> */
    private function statefulMutationHeaders(): array
    {
        return [
            ...$this->statefulHeaders(),
            'X-XSRF-TOKEN' => 'onboarding-lifecycle-test-token',
        ];
    }

    /** @return array<string, string> */
    private function statefulHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }
}
