<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Models\Course;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
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

final class ResourceAuthorizationTest extends TestCase
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

    public function test_resources_require_an_authenticated_verified_browser_session(): void
    {
        $guest = $this->withHeaders($this->headers())->getJson('/api/v1/resources');
        $this->assertApiError($guest, 401, ApiErrorCode::AuthenticationRequired);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web');
        $denied = $this->withHeaders($this->headers())->getJson('/api/v1/resources');
        $this->assertApiError($denied, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_foreign_resource_objects_relationships_and_filters_are_concealed(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $owner->getKey()]);
        $resource = Resource::factory()->file()->forCourse($course)->create();
        StoredFile::factory()->forResource($resource)->ready()->create();
        $this->actingAs($attacker, 'web');

        foreach ([
            ['GET', "/api/v1/resources/{$resource->public_id}", []],
            ['PUT', "/api/v1/resources/{$resource->public_id}", [
                'expected_version' => 1,
                'kind' => 'file',
                'title' => 'Attempted overwrite',
                'description' => null,
                'topic' => null,
                'course_id' => null,
            ]],
            ['DELETE', "/api/v1/resources/{$resource->public_id}", ['expected_version' => 1]],
            ['POST', "/api/v1/resources/{$resource->public_id}/upload-url", ['expected_version' => 1]],
            ['POST', "/api/v1/resources/{$resource->public_id}/confirm", ['expected_version' => 1]],
            ['POST', "/api/v1/resources/{$resource->public_id}/download", []],
            ['POST', "/api/v1/resources/{$resource->public_id}/cancel", ['expected_version' => 1]],
        ] as [$method, $uri, $payload]) {
            $response = $this->withHeaders($this->headers())->json($method, $uri, $payload);
            $this->assertApiError($response, 404, ApiErrorCode::ResourceNotFound);
        }

        $foreignLinkCourse = $this->withHeaders($this->headers())
            ->postJson('/api/v1/resources/links', [
                'title' => 'Probe foreign course',
                'description' => null,
                'topic' => null,
                'course_id' => $course->public_id,
                'url' => 'https://example.edu/probe',
            ]);
        $this->assertApiError($foreignLinkCourse, 404, ApiErrorCode::ResourceNotFound);

        $foreignFilter = $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?course_id='.$course->public_id);
        $this->assertApiError($foreignFilter, 404, ApiErrorCode::ResourceNotFound);

        $this->assertDatabaseHas('resources', [
            'id' => $resource->getKey(),
            'user_id' => $owner->getKey(),
            'version' => 1,
        ]);
    }

    #[DataProvider('resourceRouteProvider')]
    public function test_resource_routes_use_the_required_security_stack(
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
    public static function resourceRouteProvider(): iterable
    {
        yield 'resource list' => ['resources.index', 'resources.read'];
        yield 'link create' => ['resources.links.store', 'resources.write'];
        yield 'file initiate' => ['resources.files.store', 'resources.upload'];
        yield 'resource show' => ['resources.show', 'resources.read'];
        yield 'resource update' => ['resources.update', 'resources.write'];
        yield 'resource delete' => ['resources.destroy', 'resources.destructive'];
        yield 'upload retry' => ['resources.upload.retry', 'resources.upload'];
        yield 'upload confirm' => ['resources.upload.confirm', 'resources.upload'];
        yield 'download grant' => ['resources.download', 'resources.download'];
        yield 'upload cancel' => ['resources.upload.cancel', 'resources.destructive'];
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'resource-authorization-test-token',
        ];
    }
}
