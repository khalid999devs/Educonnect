<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Mentor\Enums\MentorVerificationState;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminMentorVerificationTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithAdminApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    public function test_admin_lists_all_mentor_profiles_including_unverified(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        MentorProfile::factory()->create(['verification_state' => MentorVerificationState::Unverified->value]);
        MentorProfile::factory()->create(['verification_state' => MentorVerificationState::Verified->value]);

        $this->signInAsAdmin($admin);

        $all = $this->adminGet('/api/v1/admin/mentors');
        $all->assertOk();
        $this->assertCount(2, $all->json('data'));

        $unverified = $this->adminGet('/api/v1/admin/mentors?verification_state=unverified');
        $unverified->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.verification_state', MentorVerificationState::Unverified->value);
    }

    public function test_admin_verifies_a_mentor_with_optimistic_concurrency_and_audit(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $profile = MentorProfile::factory()->create([
            'verification_state' => MentorVerificationState::Unverified->value,
            'version' => 1,
        ]);

        $this->signInAsAdmin($admin);

        $response = $this->adminPatch("/api/v1/admin/mentors/{$profile->public_id}/verification", [
            'verification_state' => MentorVerificationState::Verified->value,
            'expected_version' => 1,
            'reason' => 'Credentials confirmed with the institution.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.verification_state', MentorVerificationState::Verified->value)
            ->assertJsonPath('data.version', 2);
        $this->assertDatabaseHas('audit_events', [
            'action' => AuditAction::MentorVerificationChanged->value,
            'subject_type' => 'mentor_profile',
            'subject_id' => $profile->public_id,
        ]);
    }

    public function test_verification_rejects_a_stale_version_and_a_no_op_transition(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $profile = MentorProfile::factory()->create([
            'verification_state' => MentorVerificationState::Verified->value,
            'version' => 3,
        ]);

        $this->signInAsAdmin($admin);

        $stale = $this->adminPatch("/api/v1/admin/mentors/{$profile->public_id}/verification", [
            'verification_state' => MentorVerificationState::Unverified->value,
            'expected_version' => 1,
            'reason' => 'Stale version.',
        ]);
        $this->assertApiError($stale, 409, ApiErrorCode::Conflict);

        $noop = $this->adminPatch("/api/v1/admin/mentors/{$profile->public_id}/verification", [
            'verification_state' => MentorVerificationState::Verified->value,
            'expected_version' => 3,
            'reason' => 'Already verified.',
        ]);
        $this->assertApiError($noop, 409, ApiErrorCode::Conflict);
    }

    public function test_a_moderator_cannot_curate_mentors(): void
    {
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create([
            'email' => 'mod@example.com',
            'password' => 'secret123',
        ]);
        $profile = MentorProfile::factory()->create([
            'verification_state' => MentorVerificationState::Unverified->value,
            'version' => 1,
        ]);

        $this->signInAsAdmin($moderator);

        $this->assertApiError($this->adminGet('/api/v1/admin/mentors'), 403, ApiErrorCode::AuthorizationDenied);
        $this->assertApiError(
            $this->adminPatch("/api/v1/admin/mentors/{$profile->public_id}/verification", [
                'verification_state' => MentorVerificationState::Verified->value,
                'expected_version' => 1,
                'reason' => 'No permission.',
            ]),
            403,
            ApiErrorCode::AuthorizationDenied,
        );
    }
}
