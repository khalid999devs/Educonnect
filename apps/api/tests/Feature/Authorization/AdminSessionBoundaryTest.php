<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Users\Models\User;
use App\Http\Middleware\RequireBrowserSurface;
use App\Http\Middleware\RequireStatefulSpaSession;
use App\Support\ApiErrorCode;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Http\Kernel as FoundationHttpKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class AdminSessionBoundaryTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

    private const ADMIN_ORIGIN = 'https://admin.educonnect.test';

    private const STUDENT_ORIGIN = 'https://web.educonnect.test';

    /** @var array{admin: array<string, string>, student: array<string, string>} */
    private array $browserCookies = [
        'admin' => [],
        'student' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }

    public function test_only_verified_users_with_admin_access_can_start_an_admin_session(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'last_login_at' => null,
        ]);
        $student = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'secret123',
        ]);
        $unverifiedAdministrator = User::factory()
            ->withRole(RoleKey::Admin)
            ->unverified()
            ->create([
                'email' => 'unverified-admin@example.com',
                'password' => 'secret123',
            ]);

        $studentDenied = $this->adminLogin($student->email, 'secret123');
        $unverifiedDenied = $this->adminLogin($unverifiedAdministrator->email, 'secret123');
        $wrongPasswordDenied = $this->adminLogin($administrator->email, 'wrong-password');

        foreach ([$studentDenied, $unverifiedDenied, $wrongPasswordDenied] as $response) {
            $this->assertApiError($response, 422, ApiErrorCode::ValidationFailed);
        }

        $this->assertSame(
            $studentDenied->json('error.details.fields.email.0'),
            $unverifiedDenied->json('error.details.fields.email.0'),
        );
        $this->assertSame(
            $studentDenied->json('error.details.fields.email.0'),
            $wrongPasswordDenied->json('error.details.fields.email.0'),
        );
        $this->assertGuest('admin');

        $login = $this->adminLogin($administrator->email, 'secret123');
        $capabilities = $login->json('data.authorization.capabilities');

        $login
            ->assertOk()
            ->assertJsonPath('data.user.id', $administrator->public_id)
            ->assertJsonPath('data.user.primary_role', RoleKey::Admin->value)
            ->assertJsonPath('data.authorization.roles', [RoleKey::Admin->value]);
        $this->assertIsArray($capabilities);
        $this->assertContains(CapabilityKey::AdminAccess->value, $capabilities);
        $this->assertSame($capabilities, $this->sorted($capabilities));
        $this->assertAuthenticatedAs($administrator, 'admin');
        $this->assertGuest('web');
        $this->assertNotNull($administrator->refresh()->last_login_at);

        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $administrator->public_id);
    }

    public function test_admin_capability_is_checked_live_but_logout_remains_available_after_revocation(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $this->adminLogin($administrator->email, 'secret123')->assertOk();

        DB::table('role_capability')
            ->where('role_id', DB::table('roles')->where('key', RoleKey::Admin->value)->value('id'))
            ->where('capability_id', DB::table('capabilities')
                ->where('key', CapabilityKey::AdminAccess->value)
                ->value('id'))
            ->delete();

        $denied = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/me');

        $this->assertApiError($denied, 403, ApiErrorCode::AuthorizationDenied);

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/auth/logout')
            ->assertOk()
            ->assertJsonPath('data', null);
        $this->assertGuest('admin');
    }

    public function test_admin_login_has_an_independent_account_rate_limit(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertApiError(
                $this->adminLogin('unknown-admin@example.com', 'wrong-password'),
                422,
                ApiErrorCode::ValidationFailed,
            );
        }

        $limited = $this->adminLogin('unknown-admin@example.com', 'wrong-password');

        $this->assertApiError($limited, 429, ApiErrorCode::RateLimited);
        $this->assertNotNull($limited->headers->get('Retry-After'));
    }

    public function test_admin_session_is_invalidated_when_the_password_hash_changes(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $this->adminLogin($administrator->email, 'secret123')->assertOk();

        DB::table('users')
            ->where('id', $administrator->getKey())
            ->update(['password' => Hash::make('replacement-password')]);
        $auth = $this->app->make(AuthManager::class);
        $auth->forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $auth->shouldUse('web');

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/me');

        $this->assertApiError($response, 401, ApiErrorCode::AuthenticationRequired);
        $this->assertGuest('admin');
    }

    public function test_browser_surface_headers_and_production_host_are_default_denied(): void
    {
        $missingSurface = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $studentSurface = $this->withHeaders($this->studentHeaders())
            ->postJson('/api/v1/admin/auth/login', [
                'email' => 'admin@example.com',
                'password' => 'secret123',
            ]);
        $adminSurface = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => 'student@example.com',
                'password' => 'secret123',
            ]);

        $this->assertSame(400, $missingSurface->getStatusCode(), 'missing surface status');
        $this->assertSame(404, $studentSurface->getStatusCode(), 'student-to-admin surface status');
        $this->assertSame(404, $adminSurface->getStatusCode(), 'admin-to-student surface status');
        $this->assertApiError($missingSurface, 400, ApiErrorCode::BadRequest);
        $this->assertApiError($studentSurface, 404, ApiErrorCode::ResourceNotFound);
        $this->assertApiError($adminSurface, 404, ApiErrorCode::ResourceNotFound);

        $this->app->instance('env', 'production');
        config()->set([
            'app.frontend_url' => self::STUDENT_ORIGIN,
            'app.admin_url' => self::ADMIN_ORIGIN,
            'cors.allowed_origins' => [self::STUDENT_ORIGIN, self::ADMIN_ORIGIN],
            'sanctum.stateful' => ['web.educonnect.test', 'admin.educonnect.test'],
        ]);
        $this->assertTrue($this->app->environment('production'));

        $wrongHost = $this->call(
            'GET',
            self::STUDENT_ORIGIN.'/api/v1/admin/me',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_HOST' => 'web.educonnect.test',
                'HTTP_ORIGIN' => self::ADMIN_ORIGIN,
                'HTTP_REFERER' => self::ADMIN_ORIGIN.'/',
                'HTTPS' => 'on',
                'SERVER_PORT' => 443,
            ],
        );

        $this->assertSame(404, $wrongHost->getStatusCode(), 'wrong production host status');
        $this->assertApiError($wrongHost, 404, ApiErrorCode::ResourceNotFound);
    }

    public function test_stateful_and_surface_checks_run_before_route_authentication(): void
    {
        $kernel = $this->app->make(HttpKernelContract::class);
        $this->assertInstanceOf(FoundationHttpKernel::class, $kernel);
        $priority = $kernel->getMiddlewarePriority();
        $stateful = array_search(RequireStatefulSpaSession::class, $priority, true);
        $surface = array_search(RequireBrowserSurface::class, $priority, true);
        $authentication = array_search(AuthenticatesRequests::class, $priority, true);

        $this->assertIsInt($stateful);
        $this->assertIsInt($surface);
        $this->assertIsInt($authentication);
        $this->assertLessThan($surface, $stateful);
        $this->assertLessThan($authentication, $surface);
    }

    public function test_real_browser_middleware_isolates_admin_and_student_guards_and_rotates_admin_session(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $student = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'secret123',
        ]);
        $this->enableRealBrowserBoundary();

        $csrf = $this->browserRequest('admin', 'GET', '/sanctum/csrf-cookie');
        $csrf->assertNoContent();
        $guestSessionId = $this->sessionId('admin');
        $sessionCookie = $this->responseCookie($csrf, $this->sessionCookieName());

        $this->assertNull($sessionCookie->getDomain());
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertSame('lax', $sessionCookie->getSameSite());

        $credentials = [
            'email' => $administrator->email,
            'password' => 'secret123',
        ];
        $withoutCsrf = $this->browserRequest(
            'admin',
            'POST',
            '/api/v1/admin/auth/login',
            $credentials,
            captureCookies: false,
        );
        $this->assertApiError($withoutCsrf, 419, ApiErrorCode::CsrfTokenMismatch);

        $this->browserRequest(
            'admin',
            'POST',
            '/api/v1/admin/auth/login',
            $credentials,
            xsrfToken: $this->xsrfToken('admin'),
        )->assertOk();

        $adminSessionId = $this->sessionId('admin');
        $this->assertNotSame($guestSessionId, $adminSessionId);
        $this->assertDatabaseMissing('sessions', ['id' => $guestSessionId]);
        $this->assertDatabaseHas('sessions', [
            'id' => $adminSessionId,
            'user_id' => $administrator->getKey(),
        ]);

        $this->browserRequest('admin', 'GET', '/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $administrator->public_id);

        $adminCookieOnStudent = $this->browserRequest(
            'student',
            'GET',
            '/api/v1/me',
            cookies: $this->browserCookies['admin'],
            captureCookies: false,
        );
        $this->assertApiError($adminCookieOnStudent, 401, ApiErrorCode::AuthenticationRequired);

        $this->browserRequest('student', 'GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest(
            'student',
            'POST',
            '/api/v1/auth/login',
            ['email' => $student->email, 'password' => 'secret123'],
            xsrfToken: $this->xsrfToken('student'),
        )->assertOk();

        $studentCookieOnAdmin = $this->browserRequest(
            'admin',
            'GET',
            '/api/v1/admin/me',
            cookies: $this->browserCookies['student'],
            captureCookies: false,
        );
        $this->assertApiError($studentCookieOnAdmin, 401, ApiErrorCode::AuthenticationRequired);

        $wrongSurface = $this->browserRequest(
            'student',
            'POST',
            '/api/v1/admin/auth/login',
            $credentials,
            xsrfToken: $this->xsrfToken('student'),
            captureCookies: false,
        );
        $this->assertApiError($wrongSurface, 404, ApiErrorCode::ResourceNotFound);

        DB::table('users')
            ->where('id', $administrator->getKey())
            ->update(['password' => Hash::make('replacement-password')]);

        $staleAdminSession = $this->browserRequest('admin', 'GET', '/api/v1/admin/me');
        $this->assertApiError($staleAdminSession, 401, ApiErrorCode::AuthenticationRequired);
        $this->assertDatabaseMissing('sessions', ['id' => $adminSessionId]);
    }

    private function adminLogin(string $email, string $password): TestResponse
    {
        return $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);
    }

    /** @return array<string, string> */
    private function adminHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ];
    }

    /** @return array<string, string> */
    private function studentHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }

    private function enableRealBrowserBoundary(): void
    {
        $this->app->instance('env', 'local');

        config()->set([
            'app.env' => 'local',
            'app.frontend_url' => self::STUDENT_ORIGIN,
            'app.admin_url' => self::ADMIN_ORIGIN,
            'cors.allowed_origins' => [self::STUDENT_ORIGIN, self::ADMIN_ORIGIN],
            'cors.allowed_origins_patterns' => [],
            'cors.supports_credentials' => true,
            'sanctum.stateful' => ['web.educonnect.test', 'admin.educonnect.test'],
            'sanctum.guard' => ['web'],
            'session.cookie' => '__Host-educonnect-session',
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

    /**
     * @param  'admin'|'student'  $surface
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>|null  $cookies
     */
    private function browserRequest(
        string $surface,
        string $method,
        string $path,
        array $payload = [],
        ?string $xsrfToken = null,
        ?array $cookies = null,
        bool $captureCookies = true,
    ): TestResponse {
        $this->resetRequestState();
        $origin = $surface === 'admin' ? self::ADMIN_ORIGIN : self::STUDENT_ORIGIN;
        $host = (string) parse_url($origin, PHP_URL_HOST);
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_HOST' => $host,
            'HTTP_ORIGIN' => $origin,
            'HTTP_REFERER' => $origin.'/',
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
            $origin.$path,
            cookies: $cookies ?? $this->browserCookies[$surface],
            server: $server,
            content: $content,
        );

        if ($captureCookies) {
            $this->captureResponseCookies($surface, $response);
        }

        return $response;
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

    /** @param 'admin'|'student' $surface */
    private function captureResponseCookies(string $surface, TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() <= time()) {
                unset($this->browserCookies[$surface][$cookie->getName()]);

                continue;
            }

            $this->browserCookies[$surface][$cookie->getName()] = $cookie->getValue();
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

    /** @param 'admin'|'student' $surface */
    private function sessionId(string $surface): string
    {
        $encryptedCookie = $this->browserCookies[$surface][$this->sessionCookieName()] ?? null;
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

    /** @param 'admin'|'student' $surface */
    private function xsrfToken(string $surface): string
    {
        $token = $this->browserCookies[$surface]['XSRF-TOKEN'] ?? null;
        $this->assertIsString($token);

        return $token;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values, SORT_STRING);

        return $values;
    }
}
