<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

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
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Dashboard\Concerns\InteractsWithDashboard;
use Tests\TestCase;

final class DashboardAuthorizationTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithDashboard;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_dashboard_requires_an_authenticated_verified_session_and_live_capability(): void
    {
        $guest = $this->withHeaders($this->headers())->getJson($this->url());
        $this->assertApiError($guest, 401, ApiErrorCode::AuthenticationRequired);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web');
        $unverifiedResponse = $this->withHeaders($this->headers())->getJson($this->url());
        $this->assertApiError($unverifiedResponse, 403, ApiErrorCode::AuthorizationDenied);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        DB::table('role_capability')
            ->where('role_id', DB::table('roles')->where('key', RoleKey::Student->value)->value('id'))
            ->where('capability_id', DB::table('capabilities')
                ->where('key', CapabilityKey::AcademicManageOwn->value)
                ->value('id'))
            ->delete();

        $capabilityDenied = $this->withHeaders($this->headers())->getJson($this->url());
        $this->assertApiError($capabilityDenied, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_admin_guard_and_wrong_browser_surface_cannot_read_the_dashboard(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create();
        $this->actingAs($administrator, 'admin');
        $wrongGuard = $this->withHeaders($this->headers())->getJson($this->url());
        $this->assertApiError($wrongGuard, 401, ApiErrorCode::AuthenticationRequired);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        $wrongSurface = $this->withHeaders([
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ])->getJson($this->url());
        $this->assertApiError($wrongSurface, 404, ApiErrorCode::ResourceNotFound);
    }

    public function test_dashboard_route_uses_the_required_student_security_stack(): void
    {
        $route = Route::getRoutes()->getByName('dashboard.show');
        self::assertNotNull($route);

        $middleware = $this->app->make(Router::class)->gatherRouteMiddleware($route);
        $expected = [
            RequireStatefulSpaSession::class,
            RequireBrowserSurface::class.':'.RequireBrowserSurface::STUDENT,
            Authenticate::class.':sanctum',
            RequireVerifiedEmail::class,
            ThrottleRequests::class.':dashboard.read',
            Authorize::class.':'.CapabilityKey::AcademicManageOwn->value,
        ];
        $position = -1;

        foreach ($expected as $middlewareName) {
            $next = array_search($middlewareName, $middleware, true);
            self::assertIsInt($next, "Missing middleware [{$middlewareName}] on the dashboard route.");
            self::assertGreaterThan($position, $next);
            $position = $next;
        }
    }

    public function test_invalid_timezone_and_unknown_fields_are_rejected(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student, 'web');

        $missing = $this->withHeaders($this->headers())->getJson('/api/v1/dashboard');
        $this->assertApiError($missing, 422, ApiErrorCode::ValidationFailed);

        $invalid = $this->withHeaders($this->headers())
            ->getJson('/api/v1/dashboard?timezone=Mars/Olympus');
        $this->assertApiError($invalid, 422, ApiErrorCode::ValidationFailed);

        $unknown = $this->withHeaders($this->headers())
            ->getJson($this->url().'&scope=everything');
        $this->assertApiError($unknown, 422, ApiErrorCode::ValidationFailed);
    }
}
