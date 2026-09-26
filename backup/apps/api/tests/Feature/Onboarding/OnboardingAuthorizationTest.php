<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

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

final class OnboardingAuthorizationTest extends TestCase
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

    public function test_onboarding_requires_an_authenticated_verified_student_session(): void
    {
        $guest = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/onboarding');
        $this->assertApiError($guest, 401, ApiErrorCode::AuthenticationRequired);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web');

        $denied = $this->withHeaders($this->studentHeaders())->get('/api/v1/onboarding');

        $this->assertApiError($denied, 403, ApiErrorCode::AuthorizationDenied);
        $denied
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeaderMissing('Location');
    }

    #[DataProvider('academicRoleProvider')]
    public function test_verified_academic_roles_can_access_only_their_current_user_onboarding(
        RoleKey $role,
    ): void {
        $user = User::factory()->withRole($role)->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->studentHeaders())
            ->getJson('/api/v1/onboarding')
            ->assertOk();
    }

    #[DataProvider('nonAcademicRoleProvider')]
    public function test_privileged_roles_receive_no_onboarding_ownership_bypass(RoleKey $role): void
    {
        $user = User::factory()->withRole($role)->create();
        $this->actingAs($user, 'web');

        $denied = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/onboarding');

        $this->assertApiError($denied, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_academic_capability_is_checked_live(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        DB::table('role_capability')
            ->where('role_id', DB::table('roles')->where('key', RoleKey::Student->value)->value('id'))
            ->where('capability_id', DB::table('capabilities')
                ->where('key', CapabilityKey::AcademicManageOwn->value)
                ->value('id'))
            ->delete();

        $denied = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/onboarding');

        $this->assertApiError($denied, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_admin_guard_and_wrong_browser_surface_cannot_enter_onboarding(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create();
        $this->actingAs($administrator, 'admin');

        $wrongGuard = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/onboarding');
        $this->assertApiError($wrongGuard, 401, ApiErrorCode::AuthenticationRequired);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');

        $wrongSurface = $this->withHeaders($this->adminHeaders())->getJson('/api/v1/onboarding');
        $this->assertApiError($wrongSurface, 404, ApiErrorCode::ResourceNotFound);
    }

    public function test_bearer_token_cannot_bypass_the_stateful_onboarding_boundary(): void
    {
        $user = User::factory()->create();
        $tokenSecret = 'known-onboarding-boundary-token';
        $tokenId = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->getKey(),
            'name' => 'onboarding-boundary-test',
            'token' => hash('sha256', $tokenSecret),
            'abilities' => json_encode(['*'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $denied = $this->withHeaders([
            ...$this->studentHeaders(),
            'Authorization' => 'Bearer '.$tokenId.'|'.$tokenSecret,
        ])->getJson('/api/v1/onboarding');

        $this->assertApiError($denied, 401, ApiErrorCode::AuthenticationRequired);
    }

    #[DataProvider('onboardingRouteProvider')]
    public function test_onboarding_routes_use_the_approved_security_stack_in_order(
        string $routeName,
        string $rateLimiter,
    ): void {
        $route = Route::getRoutes()->getByName($routeName);

        self::assertNotNull($route);

        $middleware = $this->app->make(Router::class)->gatherRouteMiddleware($route);
        $expectedMiddleware = [
            RequireStatefulSpaSession::class,
            RequireBrowserSurface::class.':'.RequireBrowserSurface::STUDENT,
            Authenticate::class.':sanctum',
            RequireVerifiedEmail::class,
            ThrottleRequests::class.':'.$rateLimiter,
            Authorize::class.':'.CapabilityKey::AcademicManageOwn->value,
        ];
        $previousPosition = -1;

        foreach ($expectedMiddleware as $expected) {
            $position = array_search($expected, $middleware, true);

            self::assertIsInt($position, "Missing middleware [{$expected}] on route [{$routeName}].");
            self::assertGreaterThan(
                $previousPosition,
                $position,
                "Middleware [{$expected}] is out of order on route [{$routeName}].",
            );

            $previousPosition = $position;
        }
    }

    public function test_owner_and_server_managed_fields_are_rejected_without_mutating_onboarding(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->studentHeaders())
            ->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('data.onboarding.version', 0);

        $rejected = $this->withHeaders($this->studentHeaders())
            ->putJson('/api/v1/onboarding/steps/institution', [
                'expected_version' => 0,
                'state' => 'completed',
                'data' => [
                    'institution_name' => 'KUET',
                    'institution_country_code' => 'BD',
                ],
                'user_id' => $otherUser->getKey(),
                'profile_id' => 999,
                'completed_at' => now()->toISOString(),
                'version' => 999,
                'current_step' => 'goals',
            ]);

        $this->assertApiError($rejected, 422, ApiErrorCode::ValidationFailed);
        $this->assertStringNotContainsString('KUET', (string) $rejected->getContent());
        $this->withHeaders($this->studentHeaders())
            ->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('data.onboarding.version', 0);
    }

    /**
     * @return iterable<string, array{RoleKey}>
     */
    public static function academicRoleProvider(): iterable
    {
        yield 'student' => [RoleKey::Student];
        yield 'mentor' => [RoleKey::Mentor];
        yield 'moderator' => [RoleKey::Moderator];
    }

    /**
     * @return iterable<string, array{RoleKey}>
     */
    public static function nonAcademicRoleProvider(): iterable
    {
        yield 'admin' => [RoleKey::Admin];
        yield 'super admin' => [RoleKey::SuperAdmin];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function onboardingRouteProvider(): iterable
    {
        yield 'read' => ['onboarding.show', 'onboarding.read'];
        yield 'step write' => ['onboarding.steps.update', 'onboarding.write'];
        yield 'completion write' => ['onboarding.completion.update', 'onboarding.complete'];
    }

    /** @return array<string, string> */
    private function studentHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
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
