<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Auth\AuthManager;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class StatefulBrowserAuthenticationTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

    private const ORIGIN = 'https://web.educonnect.test';

    /** @var array<string, string> */
    private array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel intentionally bypasses CSRF checks in the testing environment.
        // This suite changes only the already-booted app environment so the real
        // middleware path runs without invoking production boot validation.
        $this->app->instance('env', 'local');

        config()->set([
            'app.env' => 'local',
            'app.frontend_url' => self::ORIGIN,
            'cors.allowed_origins' => [self::ORIGIN],
            'cors.allowed_origins_patterns' => [],
            'cors.supports_credentials' => true,
            'sanctum.stateful' => ['web.educonnect.test'],
            'session.cookie' => 'educonnect-browser-test-session',
            'session.domain' => null,
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.http_only' => true,
            'session.lottery' => [0, 100],
            'session.partitioned' => false,
            'session.path' => '/',
            'session.same_site' => 'lax',
            'session.secure' => true,
        ]);

        $this->resetRequestState();
    }

    public function test_same_origin_cookie_auth_enforces_csrf_and_accepts_the_xsrf_cookie_token(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'secret123',
        ]);

        $csrf = $this->browserRequest('GET', '/sanctum/csrf-cookie');

        $csrf->assertNoContent();
        $this->assertFalse($this->app->runningUnitTests());

        $sessionCookie = $this->responseCookie($csrf, $this->sessionCookieName());
        $xsrfCookie = $this->responseCookie($csrf, 'XSRF-TOKEN');

        $this->assertNull($sessionCookie->getDomain());
        $this->assertSame('/', $sessionCookie->getPath());
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertSame('lax', $sessionCookie->getSameSite());
        $this->assertNull($xsrfCookie->getDomain());
        $this->assertTrue($xsrfCookie->isSecure());
        $this->assertFalse($xsrfCookie->isHttpOnly());
        $this->assertSame('lax', $xsrfCookie->getSameSite());

        $credentials = [
            'email' => $user->email,
            'password' => 'secret123',
        ];

        $missingToken = $this->browserRequest(
            'POST',
            '/api/v1/auth/login',
            $credentials,
            captureCookies: false,
        );
        $invalidToken = $this->browserRequest(
            'POST',
            '/api/v1/auth/login',
            $credentials,
            xsrfToken: 'not-a-valid-xsrf-token',
            captureCookies: false,
        );

        $this->assertApiError($missingToken, 419, ApiErrorCode::CsrfTokenMismatch);
        $this->assertApiError($invalidToken, 419, ApiErrorCode::CsrfTokenMismatch);

        $this->browserRequest(
            'POST',
            '/api/v1/auth/login',
            $credentials,
            xsrfToken: $this->xsrfToken(),
        )
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->public_id);

        $onboardingPayload = [
            'expected_version' => 0,
            'state' => 'completed',
            'data' => [
                'institution_name' => 'KUET',
                'institution_country_code' => 'BD',
            ],
        ];
        $missingOnboardingToken = $this->browserRequest(
            'PUT',
            '/api/v1/onboarding/steps/institution',
            $onboardingPayload,
            captureCookies: false,
        );

        $this->assertApiError($missingOnboardingToken, 419, ApiErrorCode::CsrfTokenMismatch);

        $this->browserRequest(
            'PUT',
            '/api/v1/onboarding/steps/institution',
            $onboardingPayload,
            xsrfToken: $this->xsrfToken(),
        )
            ->assertOk()
            ->assertJsonPath('data.onboarding.version', 1)
            ->assertJsonPath('data.onboarding.profile.institution_name', 'KUET');
    }

    public function test_login_rotates_the_database_session_and_the_old_cookie_remains_unauthenticated(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'secret123',
        ]);

        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();

        $guestCookies = $this->cookies;
        $guestSessionId = $this->sessionIdFromCookies($guestCookies);

        $this->assertDatabaseHas('sessions', [
            'id' => $guestSessionId,
            'user_id' => null,
        ]);

        $this->browserRequest(
            'POST',
            '/api/v1/auth/login',
            [
                'email' => $user->email,
                'password' => 'secret123',
            ],
            xsrfToken: $this->xsrfToken(),
        )->assertOk();

        $authenticatedSessionId = $this->sessionIdFromCookies($this->cookies);

        $this->assertNotSame($guestSessionId, $authenticatedSessionId);
        $this->assertDatabaseHas('sessions', [
            'id' => $authenticatedSessionId,
            'user_id' => $user->getKey(),
        ]);

        $oldCookieResponse = $this->browserRequest(
            'GET',
            '/api/v1/me',
            cookies: $guestCookies,
            captureCookies: false,
        );

        $this->assertApiError($oldCookieResponse, 401, ApiErrorCode::AuthenticationRequired);

        $this->browserRequest('GET', '/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->public_id);

        $this->browserRequest('GET', '/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->public_id);
    }

    public function test_logout_destroys_the_authenticated_session_and_rejects_its_old_cookie(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'secret123',
        ]);

        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest(
            'POST',
            '/api/v1/auth/login',
            [
                'email' => $user->email,
                'password' => 'secret123',
            ],
            xsrfToken: $this->xsrfToken(),
        )->assertOk();

        $authenticatedCookies = $this->cookies;
        $authenticatedSessionId = $this->sessionIdFromCookies($authenticatedCookies);

        $this->assertDatabaseHas('sessions', [
            'id' => $authenticatedSessionId,
            'user_id' => $user->getKey(),
        ]);

        $this->browserRequest(
            'POST',
            '/api/v1/auth/logout',
            xsrfToken: $this->xsrfToken(),
        )
            ->assertOk()
            ->assertJsonPath('data', null);

        $replacementSessionId = $this->sessionIdFromCookies($this->cookies);

        $this->assertNotSame($authenticatedSessionId, $replacementSessionId);
        $this->assertDatabaseMissing('sessions', ['id' => $authenticatedSessionId]);
        $this->assertDatabaseHas('sessions', [
            'id' => $replacementSessionId,
            'user_id' => null,
        ]);

        $oldCookieResponse = $this->browserRequest(
            'GET',
            '/api/v1/me',
            cookies: $authenticatedCookies,
            captureCookies: false,
        );
        $replacementCookieResponse = $this->browserRequest('GET', '/api/v1/me');

        $this->assertApiError($oldCookieResponse, 401, ApiErrorCode::AuthenticationRequired);
        $this->assertApiError($replacementCookieResponse, 401, ApiErrorCode::AuthenticationRequired);
    }

    public function test_disallowed_origin_is_never_echoed_by_credentialed_cors_preflight(): void
    {
        $response = $this->corsPreflight('https://attacker.example');

        $response
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', self::ORIGIN)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $this->assertNotSame(
            'https://attacker.example',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function test_bearer_token_cannot_bypass_the_stateful_browser_session_boundary(): void
    {
        $user = User::factory()->create();
        $tokenSecret = 'known-browser-boundary-token';
        $tokenId = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->getKey(),
            'name' => 'stateful-boundary-test',
            'token' => hash('sha256', $tokenSecret),
            'abilities' => json_encode(['*'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->resetRequestState();

        $response = $this->call(
            'GET',
            self::ORIGIN.'/api/v1/me',
            cookies: [],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$tokenId.'|'.$tokenSecret,
                'HTTP_HOST' => 'web.educonnect.test',
                'HTTP_ORIGIN' => self::ORIGIN,
                'HTTP_REFERER' => self::ORIGIN.'/',
                'HTTPS' => 'on',
                'SERVER_PORT' => 443,
            ],
        );

        $this->assertApiError($response, 401, ApiErrorCode::AuthenticationRequired);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>|null  $cookies
     */
    private function browserRequest(
        string $method,
        string $path,
        array $payload = [],
        ?string $xsrfToken = null,
        ?array $cookies = null,
        bool $captureCookies = true,
    ): TestResponse {
        $this->resetRequestState();

        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_HOST' => 'web.educonnect.test',
            'HTTP_ORIGIN' => self::ORIGIN,
            'HTTP_REFERER' => self::ORIGIN.'/',
            'HTTPS' => 'on',
            'SERVER_PORT' => 443,
        ];

        $content = null;

        if (! in_array($method, ['GET', 'HEAD'], true)) {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        if ($xsrfToken !== null) {
            $server['HTTP_X_XSRF_TOKEN'] = rawurldecode($xsrfToken);
        }

        $response = $this->call(
            $method,
            self::ORIGIN.$path,
            cookies: $cookies ?? $this->cookies,
            server: $server,
            content: $content,
        );

        if ($captureCookies) {
            $this->captureResponseCookies($response);
        }

        return $response;
    }

    private function corsPreflight(string $origin): TestResponse
    {
        $this->resetRequestState();

        return $this->call(
            'OPTIONS',
            self::ORIGIN.'/api/v1/auth/login',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-xsrf-token',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_HOST' => 'web.educonnect.test',
                'HTTP_ORIGIN' => $origin,
                'HTTPS' => 'on',
                'SERVER_PORT' => 443,
            ],
        );
    }

    private function resetRequestState(): void
    {
        $auth = $this->app->make(AuthManager::class);
        $auth->forgetGuards();
        $this->app->make(SessionManager::class)->forgetDrivers();
        $this->app->forgetInstance('auth.driver');
        $this->app->forgetInstance('session.store');
        $auth->shouldUse('web');
    }

    private function captureResponseCookies(TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() <= time()) {
                unset($this->cookies[$cookie->getName()]);

                continue;
            }

            $this->cookies[$cookie->getName()] = $cookie->getValue();
        }
    }

    private function responseCookie(TestResponse $response, string $name): Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        $this->fail("Response did not contain cookie [{$name}].");
    }

    /** @param array<string, string> $cookies */
    private function sessionIdFromCookies(array $cookies): string
    {
        $encryptedCookie = $cookies[$this->sessionCookieName()] ?? null;
        $this->assertIsString($encryptedCookie);

        /** @var Encrypter $encrypter */
        $encrypter = $this->app->make('encrypter');
        $decryptedCookie = $encrypter->decrypt($encryptedCookie, false);
        $this->assertIsString($decryptedCookie);

        $sessionId = CookieValuePrefix::validate(
            $this->sessionCookieName(),
            $decryptedCookie,
            $encrypter->getAllKeys(),
        );
        $this->assertIsString($sessionId);

        return $sessionId;
    }

    private function sessionCookieName(): string
    {
        $name = config('session.cookie');
        $this->assertIsString($name);

        return $name;
    }

    private function xsrfToken(): string
    {
        $token = $this->cookies['XSRF-TOKEN'] ?? null;
        $this->assertIsString($token);

        return $token;
    }
}
