<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Models\IntakeSuggestion;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Feature\Intake\Concerns\InteractsWithIntake;
use Tests\TestCase;

final class IntakeOpenApiContractTest extends TestCase
{
    use InteractsWithIntake;
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
        $this->fakeDns();
    }

    public function test_every_student_intake_operation_matches_the_live_openapi_contract(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $resource = Resource::factory()->file()->create([
            'user_id' => $user->getKey(),
            'title' => 'Contract Notes File',
        ]);
        StoredFile::factory()->forResource($resource)->state(['declared_mime_type' => 'text/plain'])->ready()->create();
        $failed = IntakeItem::factory()->failedRetryable()->create(['user_id' => $user->getKey()]);
        $this->actingAs($user, 'web');

        $linkId = $this->withHeaders($this->headers())
            ->postJson('/api/v1/intake/links', [
                'url' => 'https://university.example.edu/contract-syllabus',
                'context' => 'Contract test link.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.state', 'queued')
            ->json('data.id');

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/intake/files', ['resource_id' => $resource->public_id])
            ->assertCreated()
            ->assertJsonPath('data.source.resource.id', $resource->public_id);

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/intake?state=queued&per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/intake/{$linkId}")
            ->assertOk()
            ->assertJsonPath('data.id', $linkId);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$failed->public_id}/retry")
            ->assertOk()
            ->assertJsonPath('data.state', 'queued');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$linkId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.state', 'cancelled');

        $reviewItem = IntakeItem::factory()->awaitingReview()->create(['user_id' => $user->getKey()]);
        $taskSuggestion = IntakeSuggestion::factory()->create(['intake_item_id' => $reviewItem->getKey()]);
        IntakeSuggestion::factory()->resource()->create(['intake_item_id' => $reviewItem->getKey()]);

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/intake/{$reviewItem->public_id}/suggestions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $taskSuggestion->public_id)
            ->assertJsonPath('data.0.status', 'proposed');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$reviewItem->public_id}/confirmation", [
                'decisions' => [
                    ['id' => $taskSuggestion->public_id, 'action' => 'apply', 'overrides' => ['title' => 'Contract Confirmed Task']],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.state', 'saved')
            ->assertJsonPath('data.classification.provider', 'rule_based');

        $this->withoutRequestValidation()
            ->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$reviewItem->public_id}/confirmation", [
                'decisions' => [['id' => 'not-a-ulid', 'action' => 'explode']],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withoutRequestValidation()
            ->withHeaders($this->headers())
            ->postJson('/api/v1/intake/links', ['url' => 'http://insecure.example.edu/x'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withoutRequestValidation()
            ->withHeaders($this->readHeaders())
            ->getJson('/api/v1/intake?state=unsupported&per_page=51')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /** @return array<string, string> */
    private function readHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'intake-contract-session');

        return $authenticatedRequest;
    }
}
