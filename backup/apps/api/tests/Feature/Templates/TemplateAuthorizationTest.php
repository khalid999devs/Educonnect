<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

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
use Tests\Feature\Templates\Concerns\InteractsWithTemplates;
use Tests\TestCase;

final class TemplateAuthorizationTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithTemplates;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_template_routes_require_an_authenticated_verified_session_and_live_capability(): void
    {
        foreach (['/api/v1/templates', '/api/v1/template-copies'] as $path) {
            $guest = $this->withHeaders($this->headers())->getJson($path);
            $this->assertApiError($guest, 401, ApiErrorCode::AuthenticationRequired);
        }

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web');
        $unverifiedResponse = $this->withHeaders($this->headers())->getJson('/api/v1/templates');
        $this->assertApiError($unverifiedResponse, 403, ApiErrorCode::AuthorizationDenied);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        DB::table('role_capability')
            ->where('role_id', DB::table('roles')->where('key', RoleKey::Student->value)->value('id'))
            ->where('capability_id', DB::table('capabilities')
                ->where('key', CapabilityKey::AcademicManageOwn->value)
                ->value('id'))
            ->delete();

        foreach (['/api/v1/templates', '/api/v1/template-copies'] as $path) {
            $capabilityDenied = $this->withHeaders($this->headers())->getJson($path);
            $this->assertApiError($capabilityDenied, 403, ApiErrorCode::AuthorizationDenied);
        }
    }

    public function test_admin_guard_and_wrong_browser_surface_cannot_enter_template_routes(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create();
        $this->actingAs($administrator, 'admin');
        $wrongGuard = $this->withHeaders($this->headers())->getJson('/api/v1/templates');
        $this->assertApiError($wrongGuard, 401, ApiErrorCode::AuthenticationRequired);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');

        foreach (['/api/v1/templates', '/api/v1/template-copies'] as $path) {
            $wrongSurface = $this->withHeaders($this->adminSurfaceHeaders())->getJson($path);
            $this->assertApiError($wrongSurface, 404, ApiErrorCode::ResourceNotFound);
        }
    }

    #[DataProvider('templateRouteProvider')]
    public function test_template_routes_use_the_required_student_security_stack(
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
    public static function templateRouteProvider(): iterable
    {
        yield 'template list' => ['templates.index', 'templates.read'];
        yield 'template detail' => ['templates.show', 'templates.read'];
        yield 'template save' => ['templates.saved.store', 'templates.preference'];
        yield 'template unsave' => ['templates.saved.destroy', 'templates.preference'];
        yield 'template dismiss' => ['templates.dismissed.store', 'templates.preference'];
        yield 'template undismiss' => ['templates.dismissed.destroy', 'templates.preference'];
        yield 'template copy' => ['templates.copies.store', 'template-copies.write'];
        yield 'copy list' => ['template-copies.index', 'template-copies.read'];
        yield 'copy detail' => ['template-copies.show', 'template-copies.read'];
        yield 'copy update' => ['template-copies.update', 'template-copies.write'];
        yield 'copy archive' => ['template-copies.archive', 'template-copies.write'];
        yield 'copy restore' => ['template-copies.restore', 'template-copies.write'];
    }

    /** @return array<string, string> */
    private function adminSurfaceHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ];
    }
}
