<?php

declare(strict_types=1);

namespace Tests\Feature\Courses;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Models\Course;
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

final class CourseAuthorizationTest extends TestCase
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

    public function test_academic_routes_require_an_authenticated_verified_session_and_live_capability(): void
    {
        $guest = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/courses');
        $this->assertApiError($guest, 401, ApiErrorCode::AuthenticationRequired);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web');
        $unverifiedResponse = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/courses');
        $this->assertApiError($unverifiedResponse, 403, ApiErrorCode::AuthorizationDenied);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        DB::table('role_capability')
            ->where('role_id', DB::table('roles')->where('key', RoleKey::Student->value)->value('id'))
            ->where('capability_id', DB::table('capabilities')
                ->where('key', CapabilityKey::AcademicManageOwn->value)
                ->value('id'))
            ->delete();

        $capabilityDenied = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/courses');
        $this->assertApiError($capabilityDenied, 403, ApiErrorCode::AuthorizationDenied);
    }

    #[DataProvider('academicRoleProvider')]
    public function test_approved_academic_roles_can_list_only_their_own_context(RoleKey $role): void
    {
        $user = User::factory()->withRole($role)->create();
        $other = User::factory()->create();
        AcademicTerm::factory()->create(['user_id' => $other->getKey()]);
        Course::factory()->create(['user_id' => $other->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->studentHeaders())
            ->getJson('/api/v1/academic-terms')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->withHeaders($this->studentHeaders())
            ->getJson('/api/v1/courses')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[DataProvider('nonAcademicRoleProvider')]
    public function test_privileged_roles_receive_no_private_academic_bypass(RoleKey $role): void
    {
        $user = User::factory()->withRole($role)->create();
        $this->actingAs($user, 'web');

        $courses = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/courses');
        $terms = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/academic-terms');

        $this->assertApiError($courses, 403, ApiErrorCode::AuthorizationDenied);
        $this->assertApiError($terms, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_admin_guard_wrong_surface_and_bearer_token_cannot_enter_academic_routes(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create();
        $this->actingAs($administrator, 'admin');
        $wrongGuard = $this->withHeaders($this->studentHeaders())->getJson('/api/v1/courses');
        $this->assertApiError($wrongGuard, 401, ApiErrorCode::AuthenticationRequired);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        $wrongSurface = $this->withHeaders($this->adminHeaders())->getJson('/api/v1/courses');
        $this->assertApiError($wrongSurface, 404, ApiErrorCode::ResourceNotFound);

        $this->app['auth']->forgetGuards();
        $tokenSecret = 'known-course-boundary-token';
        $tokenId = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => User::class,
            'tokenable_id' => $student->getKey(),
            'name' => 'course-boundary-test',
            'token' => hash('sha256', $tokenSecret),
            'abilities' => json_encode(['*'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bearer = $this->withHeaders([
            ...$this->studentHeaders(),
            'Authorization' => 'Bearer '.$tokenId.'|'.$tokenSecret,
        ])->getJson('/api/v1/courses');
        $this->assertApiError($bearer, 401, ApiErrorCode::AuthenticationRequired);
    }

    public function test_foreign_terms_and_courses_are_concealed_for_every_object_operation(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $term = AcademicTerm::factory()->create(['user_id' => $owner->getKey()]);
        $course = Course::factory()->forAcademicTerm($term)->create();
        $this->actingAs($attacker, 'web');

        foreach ([
            ['GET', "/api/v1/academic-terms/{$term->public_id}", null],
            ['PUT', "/api/v1/academic-terms/{$term->public_id}", [
                'expected_version' => 1,
                'label' => 'Stolen term',
                'starts_on' => null,
                'ends_on' => null,
            ]],
            ['DELETE', "/api/v1/academic-terms/{$term->public_id}", ['expected_version' => 1]],
            ['GET', "/api/v1/courses/{$course->public_id}", null],
            ['PUT', "/api/v1/courses/{$course->public_id}", [
                'expected_version' => 1,
                'title' => 'Stolen course',
                'code' => null,
                'description' => null,
                'term_id' => null,
            ]],
            ['PUT', "/api/v1/courses/{$course->public_id}/archive", ['expected_version' => 1]],
            ['DELETE', "/api/v1/courses/{$course->public_id}/archive", ['expected_version' => 1]],
            ['DELETE', "/api/v1/courses/{$course->public_id}", ['expected_version' => 1]],
        ] as [$method, $uri, $payload]) {
            $response = $this->withHeaders($this->studentHeaders())->json($method, $uri, $payload ?? []);
            $this->assertApiError($response, 404, ApiErrorCode::ResourceNotFound);
        }

        $this->assertDatabaseHas('academic_terms', ['id' => $term->getKey(), 'user_id' => $owner->getKey()]);
        $this->assertDatabaseHas('courses', ['id' => $course->getKey(), 'user_id' => $owner->getKey()]);
    }

    public function test_foreign_term_assignment_is_concealed_on_create_and_update(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreignTerm = AcademicTerm::factory()->create(['user_id' => $other->getKey()]);
        $course = Course::factory()->create(['user_id' => $owner->getKey()]);
        $this->actingAs($owner, 'web');

        $create = $this->withHeaders($this->studentHeaders())->postJson('/api/v1/courses', [
            'title' => 'Private term probe',
            'code' => null,
            'description' => null,
            'term_id' => $foreignTerm->public_id,
        ]);
        $this->assertApiError($create, 404, ApiErrorCode::ResourceNotFound);

        $update = $this->withHeaders($this->studentHeaders())->putJson("/api/v1/courses/{$course->public_id}", [
            'expected_version' => 1,
            'title' => $course->title,
            'code' => $course->code,
            'description' => null,
            'term_id' => $foreignTerm->public_id,
        ]);
        $this->assertApiError($update, 404, ApiErrorCode::ResourceNotFound);
        $this->assertNull($course->fresh()?->academic_term_id);
    }

    #[DataProvider('academicRouteProvider')]
    public function test_academic_routes_use_the_security_stack_and_bounded_limiter_in_order(
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
    public static function academicRouteProvider(): iterable
    {
        yield 'terms list' => ['academic-terms.index', 'academic.read'];
        yield 'terms create' => ['academic-terms.store', 'academic.write'];
        yield 'terms show' => ['academic-terms.show', 'academic.read'];
        yield 'terms update' => ['academic-terms.update', 'academic.write'];
        yield 'terms delete' => ['academic-terms.destroy', 'academic.destructive'];
        yield 'courses list' => ['courses.index', 'academic.read'];
        yield 'courses create' => ['courses.store', 'academic.write'];
        yield 'courses show' => ['courses.show', 'academic.read'];
        yield 'courses update' => ['courses.update', 'academic.write'];
        yield 'courses delete' => ['courses.destroy', 'academic.destructive'];
        yield 'courses archive' => ['courses.archive', 'academic.write'];
        yield 'courses restore' => ['courses.restore', 'academic.write'];
    }

    /** @return array<string, string> */
    private function studentHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'course-authorization-test-token',
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
