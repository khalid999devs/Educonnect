<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Tests\TestCase;

final class AuthenticationOpenApiContractTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_registration_request_and_response_match_the_openapi_contract(): void
    {
        $this->withHeaders($this->statefulMutationHeaders())
            ->postJson('/api/v1/auth/register', [
                'name' => 'Contract Student',
                'email' => 'contract@example.com',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ])
            ->assertCreated()
            ->assertJsonPath('data.user.email_verified', false);
    }

    public function test_login_and_current_user_responses_match_the_openapi_contract(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'secret123',
        ]);

        $this->withHeaders($this->statefulMutationHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'secret123',
            ])
            ->assertOk();

        $this->withoutRequestValidation()
            ->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->public_id);
    }

    public function test_enumeration_safe_password_request_matches_the_openapi_contract(): void
    {
        $this->withHeaders($this->statefulMutationHeaders())
            ->postJson('/api/v1/auth/forgot-password', [
                'email' => 'unknown@example.com',
            ])
            ->assertAccepted();
    }

    public function test_invalid_reset_and_unauthenticated_me_errors_match_the_openapi_contract(): void
    {
        $this->withHeaders($this->statefulMutationHeaders())
            ->postJson('/api/v1/auth/reset-password', [
                'token' => 'invalid-token',
                'email' => 'unknown@example.com',
                'password' => 'new-secret123',
                'password_confirmation' => 'new-secret123',
            ])
            ->assertUnprocessable();

        $this->withoutRequestValidation()
            ->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_verification_resend_and_signed_verification_responses_match_the_contract(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'web');

        $this->withoutRequestValidation()
            ->withHeaders($this->statefulMutationHeaders())
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertAccepted();

        $verificationUrl = URL::temporarySignedRoute(
            'auth.verification.verify',
            now()->addHour(),
            [
                'user' => $user->public_id,
                'hash' => hash('sha1', $user->email),
            ],
        );

        $this->withoutRequestValidation()
            ->withHeaders($this->statefulHeaders())
            ->getJson($verificationUrl)
            ->assertOk()
            ->assertJsonPath('data.user.email_verified', true);
    }

    public function test_logout_responses_match_the_openapi_contract(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $this->actingAs($user, 'web');

        $this->withoutRequestValidation()
            ->withHeaders($this->statefulMutationHeaders())
            ->postJson('/api/v1/auth/logout-all', ['password' => 'secret123'])
            ->assertOk();

        $secondUser = User::factory()->create();
        $this->actingAs($secondUser, 'web');

        $this->withoutRequestValidation()
            ->withHeaders($this->statefulMutationHeaders())
            ->postJson('/api/v1/auth/logout')
            ->assertOk();
    }

    /**
     * @return array<string, string>
     */
    private function statefulMutationHeaders(): array
    {
        return [
            ...$this->statefulHeaders(),
            'X-XSRF-TOKEN' => 'contract-test-token',
        ];
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
