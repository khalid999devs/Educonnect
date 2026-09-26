<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

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
use Tests\Feature\Intake\Concerns\InteractsWithIntake;
use Tests\TestCase;

final class IntakeAuthorizationTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithIntake;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_intake_routes_require_an_authenticated_verified_session_and_live_capability(): void
    {
        $guest = $this->withHeaders($this->headers())->getJson('/api/v1/intake');
        $this->assertApiError($guest, 401, ApiErrorCode::AuthenticationRequired);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web');
        $unverifiedResponse = $this->withHeaders($this->headers())->getJson('/api/v1/intake');
        $this->assertApiError($unverifiedResponse, 403, ApiErrorCode::AuthorizationDenied);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        DB::table('role_capability')
            ->where('role_id', DB::table('roles')->where('key', RoleKey::Student->value)->value('id'))
            ->where('capability_id', DB::table('capabilities')
                ->where('key', CapabilityKey::AcademicManageOwn->value)
                ->value('id'))
            ->delete();

        $capabilityDenied = $this->withHeaders($this->headers())->getJson('/api/v1/intake');
        $this->assertApiError($capabilityDenied, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_admin_guard_and_wrong_browser_surface_cannot_enter_intake_routes(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create();
        $this->actingAs($administrator, 'admin');
        $wrongGuard = $this->withHeaders($this->headers())->getJson('/api/v1/intake');
        $this->assertApiError($wrongGuard, 401, ApiErrorCode::AuthenticationRequired);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        $wrongSurface = $this->withHeaders([
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ])->getJson('/api/v1/intake');
        $this->assertApiError($wrongSurface, 404, ApiErrorCode::ResourceNotFound);
    }

    #[DataProvider('intakeRouteProvider')]
    public function test_intake_routes_use_the_required_student_security_stack(
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

    /** @return iterable<string, array{string, string}> */
    public static function intakeRouteProvider(): iterable
    {
        yield 'intake list' => ['intake.index', 'intake.read'];
        yield 'intake link create' => ['intake.links.store', 'intake.write'];
        yield 'intake file create' => ['intake.files.store', 'intake.write'];
        yield 'intake status' => ['intake.show', 'intake.read'];
        yield 'intake cancel' => ['intake.cancel', 'intake.write'];
        yield 'intake retry' => ['intake.retry', 'intake.write'];
        yield 'intake suggestions' => ['intake.suggestions.index', 'intake.read'];
        yield 'intake confirmation' => ['intake.confirmation.store', 'intake.write'];
    }
}
