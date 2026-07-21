<?php

declare(strict_types=1);

namespace Tests\Feature\Study;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Study\Models\StudyArtifact;
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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Study\Concerns\InteractsWithStudy;
use Tests\TestCase;

final class StudyAuthorizationTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithStudy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
        Queue::fake();
    }

    public function test_study_routes_require_an_authenticated_verified_session_and_live_capability(): void
    {
        $guest = $this->withHeaders($this->headers())->getJson('/api/v1/study/artifacts');
        $this->assertApiError($guest, 401, ApiErrorCode::AuthenticationRequired);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web');
        $unverifiedResponse = $this->withHeaders($this->headers())->getJson('/api/v1/study/artifacts');
        $this->assertApiError($unverifiedResponse, 403, ApiErrorCode::AuthorizationDenied);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        DB::table('role_capability')
            ->where('role_id', DB::table('roles')->where('key', RoleKey::Student->value)->value('id'))
            ->where('capability_id', DB::table('capabilities')
                ->where('key', CapabilityKey::AcademicManageOwn->value)
                ->value('id'))
            ->delete();

        $capabilityDenied = $this->withHeaders($this->headers())->getJson('/api/v1/study/artifacts');
        $this->assertApiError($capabilityDenied, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_admin_guard_and_wrong_browser_surface_cannot_enter_study_routes(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create();
        $this->actingAs($administrator, 'admin');
        $wrongGuard = $this->withHeaders($this->headers())->getJson('/api/v1/study/artifacts');
        $this->assertApiError($wrongGuard, 401, ApiErrorCode::AuthenticationRequired);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        $wrongSurface = $this->withHeaders([
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ])->getJson('/api/v1/study/artifacts');
        $this->assertApiError($wrongSurface, 404, ApiErrorCode::ResourceNotFound);
    }

    /**
     * Study material is private. A privileged role is not a reader of another
     * student's summaries, and the endpoint never confirms the id exists.
     */
    public function test_another_students_study_material_is_concealed_as_not_found(): void
    {
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $artifact = StudyArtifact::factory()->forItem($item)->ready()->create();

        $intruder = User::factory()->create();
        $this->actingAs($intruder, 'web');

        $read = $this->withHeaders($this->headers())
            ->getJson("/api/v1/study/artifacts/{$artifact->public_id}");
        $write = $this->withHeaders($this->headers())
            ->postJson($this->url((string) $item->public_id), ['kind' => 'quick_learn']);
        $filter = $this->withHeaders($this->headers())
            ->getJson('/api/v1/study/artifacts?item_id='.$item->public_id);

        $this->assertApiError($read, 404, ApiErrorCode::ResourceNotFound);
        $this->assertApiError($write, 404, ApiErrorCode::ResourceNotFound);
        $this->assertApiError($filter, 404, ApiErrorCode::ResourceNotFound);

        self::assertSame(1, StudyArtifact::query()->count());
    }

    public function test_a_students_own_list_never_contains_another_students_artifacts(): void
    {
        $owner = User::factory()->create();
        StudyArtifact::factory()->forItem($this->studyableItem($owner))->ready()->create();

        $other = User::factory()->create();
        StudyArtifact::factory()->forItem($this->studyableItem($other))->ready()->create();

        $this->actingAs($other, 'web');
        $response = $this->withHeaders($this->headers())->getJson('/api/v1/study/artifacts');

        $response->assertOk()->assertJsonCount(1, 'data');
        self::assertSame(1, $response->json('meta.summary.total'));
    }

    #[DataProvider('studyRouteProvider')]
    public function test_study_routes_use_the_required_student_security_stack(
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
            Authorize::class.':academic.manage-own',
        ];

        foreach ($expected as $entry) {
            self::assertContains($entry, $middleware, "{$routeName} is missing {$entry}");
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function studyRouteProvider(): array
    {
        return [
            'generate' => ['study.generations.store', 'study.generate'],
            'list' => ['study.artifacts.index', 'study.read'],
            'show' => ['study.artifacts.show', 'study.read'],
        ];
    }
}
