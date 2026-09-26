<?php

declare(strict_types=1);

namespace Tests\Feature\Guidance;

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

final class GuidanceAuthorizationTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_guidance_routes_require_an_authenticated_verified_session_and_live_capability(): void
    {
        foreach (['/api/v1/prompts', '/api/v1/workflows', '/api/v1/guidance?category=research'] as $path) {
            $guest = $this->withHeaders($this->studentHeaders())->getJson($path);
            $this->assertApiError($guest, 401, ApiErrorCode::AuthenticationRequired);
        }

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web');
        $unverifiedResponse = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/prompts');
        $this->assertApiError($unverifiedResponse, 403, ApiErrorCode::AuthorizationDenied);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        DB::table('role_capability')
            ->where('role_id', DB::table('roles')->where('key', RoleKey::Student->value)->value('id'))
            ->where('capability_id', DB::table('capabilities')
                ->where('key', CapabilityKey::AcademicManageOwn->value)
                ->value('id'))
            ->delete();

        foreach (['/api/v1/prompts', '/api/v1/workflows', '/api/v1/guidance?category=research'] as $path) {
            $capabilityDenied = $this->withHeaders($this->studentHeaders())->getJson($path);
            $this->assertApiError($capabilityDenied, 403, ApiErrorCode::AuthorizationDenied);
        }
    }

    public function test_admin_guard_and_wrong_browser_surface_cannot_enter_guidance_routes(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create();
        $this->actingAs($administrator, 'admin');
        $wrongGuard = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/prompts');
        $this->assertApiError($wrongGuard, 401, ApiErrorCode::AuthenticationRequired);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');

        foreach (['/api/v1/prompts', '/api/v1/workflows', '/api/v1/guidance?category=research'] as $path) {
            $wrongSurface = $this->withHeaders($this->adminHeaders())->getJson($path);
            $this->assertApiError($wrongSurface, 404, ApiErrorCode::ResourceNotFound);
        }
    }

    #[DataProvider('guidanceRouteProvider')]
    public function test_guidance_routes_use_the_required_student_security_stack(
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
    public static function guidanceRouteProvider(): iterable
    {
        yield 'prompt list' => ['prompts.index', 'prompts.read'];
        yield 'prompt detail' => ['prompts.show', 'prompts.read'];
        yield 'prompt save' => ['prompts.saved.store', 'prompts.preference'];
        yield 'prompt unsave' => ['prompts.saved.destroy', 'prompts.preference'];
        yield 'prompt dismiss' => ['prompts.dismissed.store', 'prompts.preference'];
        yield 'prompt undismiss' => ['prompts.dismissed.destroy', 'prompts.preference'];
        yield 'prompt copy' => ['prompts.copies.store', 'prompts.preference'];
        yield 'workflow list' => ['workflows.index', 'workflows.read'];
        yield 'workflow detail' => ['workflows.show', 'workflows.read'];
        yield 'workflow save' => ['workflows.saved.store', 'workflows.preference'];
        yield 'workflow unsave' => ['workflows.saved.destroy', 'workflows.preference'];
        yield 'workflow dismiss' => ['workflows.dismissed.store', 'workflows.preference'];
        yield 'workflow undismiss' => ['workflows.dismissed.destroy', 'workflows.preference'];
        yield 'guidance bundle' => ['guidance.show', 'guidance.read'];
    }

    /** @return array<string, string> */
    private function studentHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'guidance-authorization-test-token',
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

    private function configureBrowserBoundary(): void
    {
        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }
}
