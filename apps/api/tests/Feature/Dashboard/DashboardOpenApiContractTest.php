<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Tools\Models\Tool;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Feature\Dashboard\Concerns\InteractsWithDashboard;
use Tests\TestCase;

final class DashboardOpenApiContractTest extends TestCase
{
    use InteractsWithDashboard;
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_the_dashboard_aggregate_matches_the_live_openapi_contract(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user, 'user')->create(['due_at' => now()->addHours(2)]);
        FocusSession::factory()->for($user, 'user')->create([
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHour()->addMinutes(45),
        ]);
        Tool::factory()->published()->create();
        KnowledgeItem::factory()->for($user, 'user')->linkSource()->create();
        IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson($this->url('Asia/Dhaka'))
            ->assertOk()
            ->assertJsonPath('data.timeframe.timezone', 'Asia/Dhaka');

        $this->withoutRequestValidation()
            ->withHeaders($this->headers())
            ->getJson('/api/v1/dashboard?timezone=Not/AZone')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_the_empty_dashboard_matches_the_live_openapi_contract(): void
    {
        $empty = User::factory()->create();
        $this->actingAs($empty, 'web');

        $this->withHeaders($this->headers())
            ->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.progress.next_action', null)
            ->assertJsonPath('data.personal_rhythm.has_activity', false);
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'dashboard-contract-session');

        return $authenticatedRequest;
    }
}
