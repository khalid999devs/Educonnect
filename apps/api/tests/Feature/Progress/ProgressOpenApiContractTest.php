<?php

declare(strict_types=1);

namespace Tests\Feature\Progress;

use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Feature\Progress\Concerns\InteractsWithProgress;
use Tests\TestCase;

final class ProgressOpenApiContractTest extends TestCase
{
    use InteractsWithProgress;
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
        $this->travelTo(CarbonImmutable::parse('2026-07-15T08:00:00Z'));
    }

    public function test_the_populated_progress_aggregate_matches_the_live_openapi_contract(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user, 'user')->completed(CarbonImmutable::now())->create();
        Task::factory()->for($user, 'user')->create(['due_at' => CarbonImmutable::now()->addDay()]);
        FocusSession::factory()->for($user, 'user')->create([
            'starts_at' => CarbonImmutable::now()->subHour(),
            'ends_at' => CarbonImmutable::now()->subHour()->addMinutes(30),
        ]);
        KnowledgeItem::factory()->for($user, 'user')->linkSource()->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson($this->url('Asia/Dhaka', 'week'))
            ->assertOk()
            ->assertJsonPath('data.timeframe.timezone', 'Asia/Dhaka')
            ->assertJsonPath('data.timeframe.window', 'week');

        $this->withHeaders($this->headers())
            ->getJson($this->url('Asia/Dhaka', 'month'))
            ->assertOk()
            ->assertJsonPath('data.timeframe.window', 'month');

        $this->withHeaders($this->headers())
            ->getJson($this->url('Asia/Dhaka', 'term'))
            ->assertOk()
            ->assertJsonPath('data.timeframe.window', 'term');
    }

    public function test_the_empty_progress_aggregate_matches_the_live_openapi_contract(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->withHeaders($this->headers())
            ->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.has_activity', false)
            ->assertJsonPath('data.next_action', null)
            ->assertJsonPath('data.activity_rhythm.has_activity', false);
    }

    public function test_an_invalid_window_matches_the_live_openapi_contract(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->withoutRequestValidation()
            ->withHeaders($this->headers())
            ->getJson($this->url('UTC', 'decade'))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'progress-contract-session');

        return $authenticatedRequest;
    }
}
