<?php

declare(strict_types=1);

namespace Tests\Feature\Planner;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Models\Course;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
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
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class PlannerAuthorizationTest extends TestCase
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

    public function test_planner_requires_an_authenticated_verified_browser_session(): void
    {
        $guest = $this->withHeaders($this->headers())->getJson('/api/v1/tasks');
        $this->assertApiError($guest, 401, ApiErrorCode::AuthenticationRequired);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web');
        $denied = $this->withHeaders($this->headers())->getJson('/api/v1/tasks');
        $this->assertApiError($denied, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_foreign_planner_objects_and_relationships_are_concealed(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $owner->getKey()]);
        $task = Task::factory()->forCourse($course)->create();
        $session = FocusSession::factory()->forTask($task)->create();
        $this->actingAs($attacker, 'web');

        foreach ([
            ['GET', "/api/v1/tasks/{$task->public_id}", []],
            ['PUT', "/api/v1/tasks/{$task->public_id}", [
                'expected_version' => 1,
                'title' => 'Attempted overwrite',
                'description' => null,
                'course_id' => null,
                'due_at' => null,
            ]],
            ['PUT', "/api/v1/tasks/{$task->public_id}/status", [
                'expected_version' => 1,
                'status' => 'completed',
            ]],
            ['PUT', "/api/v1/tasks/{$task->public_id}/archive", ['expected_version' => 1]],
            ['DELETE', "/api/v1/tasks/{$task->public_id}/archive", ['expected_version' => 1]],
            ['DELETE', "/api/v1/tasks/{$task->public_id}", ['expected_version' => 1]],
            ['GET', "/api/v1/focus-sessions/{$session->public_id}", []],
            ['PUT', "/api/v1/focus-sessions/{$session->public_id}", [
                'expected_version' => 1,
                'task_id' => null,
                'course_id' => null,
                'starts_at' => '2026-07-15T10:00:00Z',
                'ends_at' => '2026-07-15T11:00:00Z',
                'note' => null,
            ]],
            ['DELETE', "/api/v1/focus-sessions/{$session->public_id}", ['expected_version' => 1]],
        ] as [$method, $uri, $payload]) {
            $response = $this->withHeaders($this->headers())->json($method, $uri, $payload);
            $this->assertApiError($response, 404, ApiErrorCode::ResourceNotFound);
        }

        $foreignCourse = $this->withHeaders($this->headers())->postJson('/api/v1/tasks', [
            'title' => 'Probe foreign course',
            'description' => null,
            'course_id' => $course->public_id,
            'due_at' => null,
        ]);
        $this->assertApiError($foreignCourse, 404, ApiErrorCode::ResourceNotFound);

        $foreignTask = $this->withHeaders($this->headers())->postJson('/api/v1/focus-sessions', [
            'task_id' => $task->public_id,
            'course_id' => null,
            'starts_at' => '2026-07-15T10:00:00Z',
            'ends_at' => '2026-07-15T11:00:00Z',
            'note' => null,
        ]);
        $this->assertApiError($foreignTask, 404, ApiErrorCode::ResourceNotFound);

        $this->assertDatabaseHas('tasks', ['id' => $task->getKey(), 'user_id' => $owner->getKey()]);
        $this->assertDatabaseHas('focus_sessions', ['id' => $session->getKey(), 'user_id' => $owner->getKey()]);
    }

    #[DataProvider('plannerRouteProvider')]
    public function test_planner_routes_use_the_required_security_stack(
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
    public static function plannerRouteProvider(): iterable
    {
        yield 'tasks list' => ['tasks.index', 'planner.read'];
        yield 'tasks create' => ['tasks.store', 'planner.write'];
        yield 'tasks show' => ['tasks.show', 'planner.read'];
        yield 'tasks update' => ['tasks.update', 'planner.write'];
        yield 'tasks delete' => ['tasks.destroy', 'planner.destructive'];
        yield 'tasks status' => ['tasks.status.update', 'planner.write'];
        yield 'tasks archive' => ['tasks.archive', 'planner.write'];
        yield 'tasks restore' => ['tasks.restore', 'planner.write'];
        yield 'focus list' => ['focus-sessions.index', 'planner.read'];
        yield 'focus create' => ['focus-sessions.store', 'planner.write'];
        yield 'focus show' => ['focus-sessions.show', 'planner.read'];
        yield 'focus update' => ['focus-sessions.update', 'planner.write'];
        yield 'focus delete' => ['focus-sessions.destroy', 'planner.destructive'];
        yield 'agenda' => ['planner.agenda', 'planner.read'];
        yield 'weekly' => ['planner.weekly', 'planner.read'];
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'planner-authorization-test-token',
        ];
    }
}
