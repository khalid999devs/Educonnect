<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Users\Models\User;
use App\Http\Middleware\RequireBrowserSurface;
use App\Http\Middleware\RequireStatefulSpaSession;
use App\Http\Middleware\RequireVerifiedEmail;
use App\Support\ApiErrorCode;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class SettingsAuthorizationTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

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

    public function test_settings_routes_require_an_authenticated_verified_session_and_live_capability(): void
    {
        $guest = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/settings');
        $this->assertApiError($guest, 401, ApiErrorCode::AuthenticationRequired);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web');
        $unverifiedResponse = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/settings');
        $this->assertApiError($unverifiedResponse, 403, ApiErrorCode::AuthorizationDenied);

        $this->app['auth']->forgetGuards();
        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        DB::table('role_capability')
            ->where('role_id', DB::table('roles')->where('key', RoleKey::Student->value)->value('id'))
            ->where('capability_id', DB::table('capabilities')
                ->where('key', CapabilityKey::AcademicManageOwn->value)
                ->value('id'))
            ->delete();

        foreach ($this->settingsRoutes() as [$method, $uri, $payload]) {
            $denied = $this->withHeaders($this->studentHeaders())->json($method, $uri, $payload);
            $this->assertApiError($denied, 403, ApiErrorCode::AuthorizationDenied);
        }
    }

    #[DataProvider('academicRoleProvider')]
    public function test_approved_roles_only_ever_see_their_own_settings(RoleKey $role): void
    {
        $user = User::factory()->withRole($role)->create(['name' => 'Own Account']);
        $stranger = User::factory()->create(['name' => 'Stranger Account']);
        $this->seedSession($stranger, 'strangeraaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->actingAs($user, 'web');

        $this->withHeaders($this->studentHeaders())
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.account.id', $user->public_id)
            ->assertJsonPath('data.account.name', 'Own Account')
            ->assertJsonPath('data.account.email', $user->email);

        $sessions = $this->withHeaders($this->studentHeaders())
            ->getJson('/api/v1/settings/sessions')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $body = $sessions->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString('strangeraaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $body);
    }

    #[DataProvider('nonAcademicRoleProvider')]
    public function test_privileged_roles_receive_no_settings_bypass(RoleKey $role): void
    {
        $user = User::factory()->withRole($role)->create();
        $this->actingAs($user, 'web');

        foreach ($this->settingsRoutes() as [$method, $uri, $payload]) {
            $response = $this->withHeaders($this->studentHeaders())->json($method, $uri, $payload);
            $this->assertApiError($response, 403, ApiErrorCode::AuthorizationDenied);
        }
    }

    public function test_one_account_can_never_read_or_write_another_accounts_settings(): void
    {
        $owner = User::factory()->create(['name' => 'Owner Account']);
        $attacker = User::factory()->create(['name' => 'Attacker Account']);
        $this->seedSession($owner, 'ownersessionaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->actingAs($attacker, 'web');

        $this->withHeaders($this->studentHeaders())
            ->putJson('/api/v1/settings/account', ['name' => 'Renamed By Attacker'])
            ->assertOk();
        $this->withHeaders($this->studentHeaders())
            ->putJson('/api/v1/settings/profile', [
                'institution_name' => 'Attacker University',
                'institution_country_code' => 'BD',
                'department' => null,
                'degree' => null,
                'major' => null,
                'year_label' => null,
                'term_label' => null,
            ])
            ->assertOk();

        // Every write landed on the attacker's own row and nothing else moved.
        $this->assertSame('Renamed By Attacker', $attacker->refresh()->name);
        $this->assertSame('Owner Account', $owner->refresh()->name);
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $owner->getKey()]);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $attacker->getKey(),
            'institution_name' => 'Attacker University',
        ]);

        $this->withHeaders($this->studentHeaders())
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.account.id', $attacker->public_id);
        $this->withHeaders($this->studentHeaders())
            ->getJson('/api/v1/settings/sessions')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_admin_guard_wrong_surface_and_bearer_token_cannot_enter_settings_routes(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create();
        $this->actingAs($administrator, 'admin');
        $wrongGuard = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/settings');
        $this->assertApiError($wrongGuard, 401, ApiErrorCode::AuthenticationRequired);

        $this->app['auth']->forgetGuards();
        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        $wrongSurface = $this->withHeaders($this->adminHeaders())->getJson('/api/v1/settings');
        $this->assertApiError($wrongSurface, 404, ApiErrorCode::ResourceNotFound);

        $this->app['auth']->forgetGuards();
        $tokenSecret = 'known-settings-boundary-token';
        $tokenId = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => User::class,
            'tokenable_id' => $student->getKey(),
            'name' => 'settings-boundary-test',
            'token' => hash('sha256', $tokenSecret),
            'abilities' => json_encode(['*'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bearer = $this->withHeaders([
            ...$this->studentHeaders(),
            'Authorization' => 'Bearer '.$tokenId.'|'.$tokenSecret,
        ])->getJson('/api/v1/settings');
        $this->assertApiError($bearer, 401, ApiErrorCode::AuthenticationRequired);
    }

    #[DataProvider('settingsRouteProvider')]
    public function test_settings_routes_use_the_security_stack_and_bounded_limiter_in_order(
        string $routeName,
        string $rateLimiter,
    ): void {
        $route = Route::getRoutes()->getByName($routeName);
        self::assertNotNull($route);

        $middleware = $this->app->make(Router::class)->gatherRouteMiddleware($route);
        $expected = [
            RequireStatefulSpaSession::class,
            RequireBrowserSurface::class.':'.RequireBrowserSurface::STUDENT,
            Authenticate::class.':sanctum',
            RequireVerifiedEmail::class,
            ThrottleRequests::class.':'.$rateLimiter,
            Authorize::class.':'.CapabilityKey::AcademicManageOwn->value,
        ];
        $position = -1;

        foreach ($expected as $middlewareName) {
            $next = array_search($middlewareName, $middleware, true);
            self::assertIsInt($next, "Missing middleware [{$middlewareName}] on route [{$routeName}].");
            self::assertGreaterThan($position, $next);
            $position = $next;
        }
    }

    /** @return iterable<string, array{RoleKey}> */
    public static function academicRoleProvider(): iterable
    {
        yield 'student' => [RoleKey::Student];
        yield 'mentor' => [RoleKey::Mentor];
        yield 'moderator' => [RoleKey::Moderator];
    }

    /** @return iterable<string, array{RoleKey}> */
    public static function nonAcademicRoleProvider(): iterable
    {
        yield 'admin' => [RoleKey::Admin];
        yield 'super admin' => [RoleKey::SuperAdmin];
    }

    /** @return iterable<string, array{string, string}> */
    public static function settingsRouteProvider(): iterable
    {
        yield 'settings show' => ['settings.show', 'settings.read'];
        yield 'settings sessions' => ['settings.sessions.index', 'settings.read'];
        yield 'settings profile' => ['settings.profile.update', 'settings.write'];
        yield 'settings account' => ['settings.account.update', 'settings.write'];
    }

    /** @return list<array{string, string, array<string, mixed>}> */
    private function settingsRoutes(): array
    {
        return [
            ['GET', '/api/v1/settings', []],
            ['GET', '/api/v1/settings/sessions', []],
            ['PUT', '/api/v1/settings/account', ['name' => 'Denied Rename']],
            ['PUT', '/api/v1/settings/profile', [
                'institution_name' => null,
                'institution_country_code' => null,
                'department' => null,
                'degree' => null,
                'major' => null,
                'year_label' => null,
                'term_label' => null,
            ]],
        ];
    }

    private function seedSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'ip_address' => '198.51.100.4',
            'user_agent' => 'Foreign session agent',
            'payload' => 'test-session-payload',
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    /** @return array<string, string> */
    private function studentHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'settings-authorization-test-token',
        ];
    }

    /** @return array<string, string> */
    private function adminHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ];
    }
}
