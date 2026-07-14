<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domains\Auth\Notifications\ResetPasswordNotification;
use App\Domains\Auth\Notifications\VerifyEmailNotification;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class AuthenticationLifecycleTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.url' => 'http://localhost',
            'app.frontend_url' => 'http://localhost:3000',
        ]);
    }

    public function test_registration_queues_an_encrypted_verification_notification(): void
    {
        Notification::fake();

        $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/register', [
            'name' => 'New Student',
            'email' => 'student@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $user = User::query()->sole();

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.email_verified', false);

        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class,
            static fn (VerifyEmailNotification $notification): bool => $notification instanceof ShouldQueue
                && $notification instanceof ShouldBeEncrypted,
        );
    }

    public function test_forgot_password_is_enumeration_safe_and_queues_an_encrypted_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'student@example.com']);

        $known = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/forgot-password', [
            'email' => '  STUDENT@example.com  ',
        ]);
        $unknown = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ]);

        $known->assertAccepted();
        $unknown->assertAccepted();
        $this->assertSame($known->json('data.message'), $unknown->json('data.message'));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'student@example.com']);
        $this->assertDatabaseCount('password_reset_tokens', 1);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user): bool {
                $url = $notification->toMail($user)->actionUrl;

                return $notification instanceof ShouldQueue
                    && $notification instanceof ShouldBeEncrypted
                    && is_string($url)
                    && str_starts_with($url, 'http://localhost:3000/reset-password?')
                    && str_contains($url, 'email=student%40example.com');
            },
        );
    }

    public function test_password_reset_changes_credentials_revokes_access_and_does_not_log_in(): void
    {
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'old-secret123',
            'remember_token' => 'remember-before-reset',
        ]);
        $originalRememberToken = $user->remember_token;
        $token = Password::broker()->createToken($user);
        $this->createAuthenticatedSession($user, 'reset-session-one');
        $this->createAuthenticatedSession($user, 'reset-session-two');
        $this->createPersonalAccessToken($user, 'reset-token');
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/reset-password', [
            'email' => 'STUDENT@example.com',
            'token' => $token,
            'password' => 'new-secret123',
            'password_confirmation' => 'new-secret123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.message', 'Your password has been reset.');

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret123', $user->password));
        $this->assertFalse(Hash::check('old-secret123', $user->password));
        $this->assertNotSame($originalRememberToken, $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->getKey()]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->getKey()]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest('web');
        Event::assertDispatchedTimes(PasswordReset::class, 1);
    }

    public function test_invalid_password_reset_does_not_change_or_revoke_any_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'old-secret123',
            'remember_token' => 'remember-before-invalid-reset',
        ]);
        $originalPassword = $user->password;
        $originalRememberToken = $user->remember_token;
        $this->createAuthenticatedSession($user, 'invalid-reset-session');
        $this->createPersonalAccessToken($user, 'invalid-reset-token');

        $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-reset-token',
            'password' => 'new-secret123',
            'password_confirmation' => 'new-secret123',
        ]);

        $this->assertApiError($response, 422, ApiErrorCode::ValidationFailed);
        $response->assertJsonStructure(['error' => ['details' => ['fields' => ['token']]]]);
        $user->refresh();
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame($originalRememberToken, $user->remember_token);
        $this->assertDatabaseHas('sessions', ['id' => 'invalid-reset-session']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'invalid-reset-token']);
    }

    public function test_expired_password_reset_token_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'old-secret123',
        ]);
        $token = Password::broker()->createToken($user);
        $this->travel(61)->minutes();

        $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-secret123',
            'password_confirmation' => 'new-secret123',
        ]);

        $this->assertApiError($response, 422, ApiErrorCode::ValidationFailed);
        $this->assertTrue(Hash::check('old-secret123', $user->refresh()->password));
    }

    public function test_password_reset_token_is_single_use(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'old-secret123',
        ]);
        $token = Password::broker()->createToken($user);

        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'first-new-secret',
            'password_confirmation' => 'first-new-secret',
        ];

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/reset-password', $payload)
            ->assertOk();

        $payload['password'] = 'second-new-secret';
        $payload['password_confirmation'] = 'second-new-secret';
        $replay = $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/reset-password', $payload);

        $this->assertApiError($replay, 422, ApiErrorCode::ValidationFailed);
        $this->assertTrue(Hash::check('first-new-secret', $user->refresh()->password));
        $this->assertFalse(Hash::check('second-new-secret', $user->password));
    }

    public function test_public_ulid_verification_link_is_authenticated_signed_and_idempotent(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $verificationUrl = $this->verificationUrl($user);

        $this->assertStringContainsString('/verify-email/'.$user->public_id.'/', $verificationUrl);
        $this->assertStringNotContainsString('/verify-email/'.$user->getKey().'/', $verificationUrl);
        $this->actingAs($user, 'web');

        $first = $this->withHeaders($this->statefulHeaders())->getJson($this->requestTarget($verificationUrl));
        $second = $this->withHeaders($this->statefulHeaders())->getJson($this->requestTarget($verificationUrl));

        $first
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->public_id)
            ->assertJsonPath('data.user.email_verified', true);
        $second
            ->assertOk()
            ->assertJsonPath('data.user.email_verified', true);
        $this->assertNotNull($user->refresh()->email_verified_at);
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_verification_link_rejects_a_tampered_hash(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = $this->verificationUrl($user);
        $tamperedUrl = str_replace(
            hash('sha1', $user->getEmailForVerification()),
            str_repeat('0', 40),
            $verificationUrl,
        );
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->statefulHeaders())->getJson($this->requestTarget($tamperedUrl));

        $this->assertApiError($response, 403, ApiErrorCode::AuthorizationDenied);
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_verification_link_rejects_an_expired_signature(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = $this->verificationUrl($user);
        $this->travel(61)->minutes();
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->statefulHeaders())->getJson($this->requestTarget($verificationUrl));

        $this->assertApiError($response, 403, ApiErrorCode::AuthorizationDenied);
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_verification_link_rejects_a_different_authenticated_user(): void
    {
        $linkOwner = User::factory()->unverified()->create();
        $otherUser = User::factory()->unverified()->create();
        $verificationUrl = $this->verificationUrl($linkOwner);
        $this->actingAs($otherUser, 'web');

        $response = $this->withHeaders($this->statefulHeaders())->getJson($this->requestTarget($verificationUrl));

        $this->assertApiError($response, 403, ApiErrorCode::AuthorizationDenied);
        $this->assertNull($linkOwner->refresh()->email_verified_at);
        $this->assertNull($otherUser->refresh()->email_verified_at);
    }

    public function test_verification_resend_queues_only_for_an_unverified_user(): void
    {
        Notification::fake();
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create();

        $this->actingAs($unverified, 'web');
        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertAccepted();

        $this->actingAs($verified, 'web');
        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertAccepted();

        Notification::assertSentTo($unverified, VerifyEmailNotification::class);
        Notification::assertNotSentTo($verified, VerifyEmailNotification::class);
    }

    public function test_logout_all_requires_the_current_password_and_revokes_all_access(): void
    {
        $user = User::factory()->create([
            'password' => 'secret123',
            'remember_token' => 'remember-before-logout-all',
        ]);
        $originalRememberToken = $user->remember_token;
        $this->createAuthenticatedSession($user, 'logout-all-session-one');
        $this->createAuthenticatedSession($user, 'logout-all-session-two');
        $this->createPersonalAccessToken($user, 'logout-all-token');
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/logout-all', [
            'password' => 'secret123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data', null);
        $this->assertGuest('web');
        $this->assertNotSame($originalRememberToken, $user->refresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->getKey()]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->getKey()]);
    }

    public function test_logout_all_with_a_wrong_password_preserves_access(): void
    {
        $user = User::factory()->create([
            'password' => 'secret123',
            'remember_token' => 'remember-before-wrong-logout-all',
        ]);
        $originalRememberToken = $user->remember_token;
        $this->createAuthenticatedSession($user, 'wrong-logout-all-session');
        $this->createPersonalAccessToken($user, 'wrong-logout-all-token');
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/logout-all', [
            'password' => 'wrong-password',
        ]);

        $this->assertApiError($response, 422, ApiErrorCode::ValidationFailed);
        $response->assertJsonStructure(['error' => ['details' => ['fields' => ['password']]]]);
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame($originalRememberToken, $user->refresh()->remember_token);
        $this->assertDatabaseHas('sessions', ['id' => 'wrong-logout-all-session']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'wrong-logout-all-token']);
    }

    private function createAuthenticatedSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'EduConnect PHPUnit',
            'payload' => 'test-session-payload',
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    private function createPersonalAccessToken(User $user, string $name): void
    {
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->getKey(),
            'name' => $name,
            'token' => hash('sha256', Str::random(40)),
            'abilities' => json_encode(['*'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function verificationUrl(User $user): string
    {
        $url = (new VerifyEmailNotification)->toMail($user)->actionUrl;
        $this->assertIsString($url);

        return $url;
    }

    private function requestTarget(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        $this->assertIsString($path);

        return $path.(is_string($query) ? '?'.$query : '');
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
