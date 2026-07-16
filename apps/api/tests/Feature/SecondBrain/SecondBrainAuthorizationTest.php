<?php

declare(strict_types=1);

namespace Tests\Feature\SecondBrain;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\SecondBrain\Models\Collection;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\ResearchTopic;
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
use Tests\Feature\SecondBrain\Concerns\InteractsWithSecondBrain;
use Tests\TestCase;

final class SecondBrainAuthorizationTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithSecondBrain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_second_brain_routes_require_an_authenticated_verified_session_and_live_capability(): void
    {
        $guest = $this->withHeaders($this->headers())->getJson('/api/v1/knowledge');
        $this->assertApiError($guest, 401, ApiErrorCode::AuthenticationRequired);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web');
        $unverifiedResponse = $this->withHeaders($this->headers())->getJson('/api/v1/knowledge');
        $this->assertApiError($unverifiedResponse, 403, ApiErrorCode::AuthorizationDenied);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        DB::table('role_capability')
            ->where('role_id', DB::table('roles')->where('key', RoleKey::Student->value)->value('id'))
            ->where('capability_id', DB::table('capabilities')
                ->where('key', CapabilityKey::AcademicManageOwn->value)
                ->value('id'))
            ->delete();

        $capabilityDenied = $this->withHeaders($this->headers())->getJson('/api/v1/knowledge');
        $this->assertApiError($capabilityDenied, 403, ApiErrorCode::AuthorizationDenied);
    }

    public function test_admin_guard_and_wrong_browser_surface_cannot_enter_second_brain_routes(): void
    {
        $administrator = User::factory()->withRole(RoleKey::Admin)->create();
        $this->actingAs($administrator, 'admin');
        $wrongGuard = $this->withHeaders($this->headers())->getJson('/api/v1/collections');
        $this->assertApiError($wrongGuard, 401, ApiErrorCode::AuthenticationRequired);

        $student = User::factory()->create();
        $this->actingAs($student, 'web');
        $wrongSurface = $this->withHeaders([
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ])->getJson('/api/v1/collections');
        $this->assertApiError($wrongSurface, 404, ApiErrorCode::ResourceNotFound);
    }

    public function test_other_users_records_are_concealed_as_not_found(): void
    {
        $owner = User::factory()->create();
        $collection = Collection::factory()->for($owner, 'user')->create();
        $item = KnowledgeItem::factory()->for($owner, 'user')->create();
        $topic = ResearchTopic::factory()->for($owner, 'user')->create();

        $intruder = User::factory()->create();
        $this->actingAs($intruder, 'web');

        $probes = [
            $this->withHeaders($this->headers())->getJson("/api/v1/collections/{$collection->public_id}"),
            $this->withHeaders($this->headers())->getJson("/api/v1/knowledge/{$item->public_id}"),
            $this->withHeaders($this->headers())->getJson("/api/v1/research-topics/{$topic->public_id}"),
            $this->withHeaders($this->headers())->putJson("/api/v1/knowledge/{$item->public_id}/tags", [
                'tags' => ['stolen'],
            ]),
            $this->withHeaders($this->headers())->postJson("/api/v1/research-topics/{$topic->public_id}/sources", [
                'knowledge_item_id' => (string) $item->public_id,
            ]),
        ];

        foreach ($probes as $probe) {
            $this->assertApiError($probe, 404, ApiErrorCode::ResourceNotFound);
        }
    }

    #[DataProvider('secondBrainRouteProvider')]
    public function test_second_brain_routes_use_the_required_student_security_stack(
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
    public static function secondBrainRouteProvider(): iterable
    {
        yield 'collection list' => ['collections.index', 'brain.read'];
        yield 'collection create' => ['collections.store', 'brain.write'];
        yield 'collection show' => ['collections.show', 'brain.read'];
        yield 'collection update' => ['collections.update', 'brain.write'];
        yield 'collection delete' => ['collections.destroy', 'brain.destructive'];
        yield 'knowledge list' => ['knowledge.index', 'brain.read'];
        yield 'knowledge create' => ['knowledge.store', 'brain.write'];
        yield 'knowledge show' => ['knowledge.show', 'brain.read'];
        yield 'knowledge update' => ['knowledge.update', 'brain.write'];
        yield 'knowledge delete' => ['knowledge.destroy', 'brain.destructive'];
        yield 'knowledge note create' => ['knowledge.notes.store', 'brain.write'];
        yield 'knowledge note update' => ['knowledge.notes.update', 'brain.write'];
        yield 'knowledge note delete' => ['knowledge.notes.destroy', 'brain.destructive'];
        yield 'knowledge tags sync' => ['knowledge.tags.sync', 'brain.write'];
        yield 'knowledge collections sync' => ['knowledge.collections.sync', 'brain.write'];
        yield 'knowledge link create' => ['knowledge.links.store', 'brain.write'];
        yield 'knowledge link delete' => ['knowledge.links.destroy', 'brain.destructive'];
        yield 'research list' => ['research-topics.index', 'brain.read'];
        yield 'research create' => ['research-topics.store', 'brain.write'];
        yield 'research show' => ['research-topics.show', 'brain.read'];
        yield 'research update' => ['research-topics.update', 'brain.write'];
        yield 'research delete' => ['research-topics.destroy', 'brain.destructive'];
        yield 'research source attach' => ['research-topics.sources.store', 'brain.write'];
        yield 'research source update' => ['research-topics.sources.update', 'brain.write'];
        yield 'research source detach' => ['research-topics.sources.destroy', 'brain.destructive'];
    }
}
