<?php

namespace Tests\Feature\Auth;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
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
        $this->postJson('/api/v1/auth/login', [
            'email' => 'student@example.com',
            'password' => 'secret123',
        ])
            ->assertBadRequest()
            ->assertExactJson([
                'message' => 'A stateful SPA session is required.',
            ]);
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
            ->assertExactJson([
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => 'Khalid Ahammed',
                        'email' => 'khalid@example.com',
                        'primary_role' => null,
                    ],
                ],
            ]);

        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_validates_required_fields_and_password_confirmation(): void
    {
        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register', [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_rejects_duplicate_email_case_insensitively(): void
    {
        User::factory()->create(['email' => 'student@example.com']);

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register', [
                'name' => 'Another Student',
                'email' => 'STUDENT@example.com',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_student_can_login_and_last_login_time_is_recorded(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'secret123',
            'last_login_at' => null,
        ]);

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => 'STUDENT@example.com',
                'password' => 'secret123',
            ])
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => 'student@example.com',
                        'primary_role' => null,
                    ],
                ],
            ]);

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

        $unknownEmail->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $wrongPassword->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->assertSame($unknownEmail->json('errors.email.0'), $wrongPassword->json('errors.email.0'));
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

        $this->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonMissingPath('data.user.password');

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertExactJson(['data' => null]);

        $this->assertGuest('web');
    }

    public function test_unauthenticated_users_cannot_access_protected_auth_endpoints(): void
    {
        $this->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/login', [
                    'email' => 'student@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertUnprocessable();
        }

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => 'student@example.com',
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests();
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
