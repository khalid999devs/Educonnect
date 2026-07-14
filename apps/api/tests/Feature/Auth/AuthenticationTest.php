<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

    public function test_first_party_spa_can_initialize_csrf_protection(): void
    {
        $this->withHeaders($this->statefulHeaders())
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_auth_endpoints_reject_requests_without_a_stateful_spa_session(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'student@example.com',
            'password' => 'secret123',
        ]);

        $this->assertApiError($response, 400, ApiErrorCode::BadRequest);
        $response->assertJsonPath('error.message', 'The request could not be processed.');
    }

    public function test_student_can_register_and_start_an_authenticated_session(): void
    {
        $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/register', [
            'name' => '  Khalid Ahammed  ',
            'email' => 'KHALID@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $user = User::query()->sole();

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.id', $user->public_id)
            ->assertJsonPath('data.user.name', 'Khalid Ahammed')
            ->assertJsonPath('data.user.email', 'khalid@example.com')
            ->assertJsonPath('data.user.primary_role', null)
            ->assertJsonStructure([
                'data' => ['user'],
                'meta' => ['request_id'],
            ]);

        $this->assertSuccessRequestId($response);
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_validates_required_fields_and_password_confirmation(): void
    {
        $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $this->assertApiError($response, 422, ApiErrorCode::ValidationFailed);
        $response->assertJsonStructure([
            'error' => [
                'details' => [
                    'fields' => ['name', 'email', 'password'],
                ],
            ],
        ]);
    }

    public function test_registration_rejects_duplicate_email_case_insensitively(): void
    {
        User::factory()->create(['email' => 'student@example.com']);

        $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/register', [
            'name' => 'Another Student',
            'email' => 'STUDENT@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $this->assertApiError($response, 422, ApiErrorCode::ValidationFailed);
        $response->assertJsonStructure(['error' => ['details' => ['fields' => ['email']]]]);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_password_inputs_reject_values_beyond_the_bcrypt_byte_limit(): void
    {
        $overlongPassword = str_repeat('é', 37);

        $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/register', [
            'name' => 'Student',
            'email' => 'student@example.com',
            'password' => $overlongPassword,
            'password_confirmation' => $overlongPassword,
        ]);

        $this->assertApiError($response, 422, ApiErrorCode::ValidationFailed);
        $response->assertJsonStructure(['error' => ['details' => ['fields' => ['password']]]]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_student_can_login_and_last_login_time_is_recorded(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'secret123',
            'last_login_at' => null,
        ]);

        $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/login', [
            'email' => 'STUDENT@example.com',
            'password' => 'secret123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->public_id)
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonPath('data.user.email', 'student@example.com')
            ->assertJsonPath('data.user.primary_role', null);

        $this->assertSuccessRequestId($response);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_invalid_login_uses_the_same_error_for_unknown_email_and_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'secret123',
            'last_login_at' => null,
        ]);

        $unknownEmail = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'wrong-password',
        ]);
        $wrongPassword = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/login', [
            'email' => 'student@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertApiError($unknownEmail, 422, ApiErrorCode::ValidationFailed);
        $this->assertApiError($wrongPassword, 422, ApiErrorCode::ValidationFailed);

        $this->assertSame(
            $unknownEmail->json('error.details.fields.email.0'),
            $wrongPassword->json('error.details.fields.email.0'),
        );
        $this->assertGuest('web');
    }

    public function test_authenticated_student_can_view_current_user_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'secret123',
        ]);

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => 'student@example.com',
                'password' => 'secret123',
            ])
            ->assertOk();

        $currentUser = $this->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/auth/me');

        $currentUser
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->public_id)
            ->assertJsonMissingPath('data.user.password');

        $this->assertSuccessRequestId($currentUser);

        $logout = $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/logout');

        $logout
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSuccessRequestId($logout);

        $this->assertGuest('web');
    }

    public function test_unauthenticated_users_cannot_access_protected_auth_endpoints(): void
    {
        $currentUser = $this->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/auth/me');

        $logout = $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/logout');

        $this->assertApiError($currentUser, 401, ApiErrorCode::AuthenticationRequired);
        $this->assertApiError($logout, 401, ApiErrorCode::AuthenticationRequired);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/login', [
                'email' => 'student@example.com',
                'password' => 'wrong-password',
            ]);

            $this->assertApiError($response, 422, ApiErrorCode::ValidationFailed);
        }

        $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/login', [
            'email' => 'student@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertApiError($response, 429, ApiErrorCode::RateLimited);
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    /**
     * @return array<string, string>
     */
    private function statefulHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }
}
