<?php

declare(strict_types=1);

namespace Tests\Feature\Study;

use App\Domains\Study\Enums\StudyArtifactKind;
use App\Domains\Study\Models\StudyArtifact;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Feature\Study\Concerns\InteractsWithStudy;
use Tests\TestCase;

final class StudyOpenApiContractTest extends TestCase
{
    use InteractsWithStudy;
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
        Queue::fake();
    }

    public function test_every_study_operation_matches_the_live_openapi_contract(): void
    {
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $ready = StudyArtifact::factory()
            ->forItem($item)
            ->kind(StudyArtifactKind::TopicExplanation)
            ->ready()
            ->create();
        $failed = StudyArtifact::factory()
            ->forItem($item)
            ->kind(StudyArtifactKind::ExamQuestions)
            ->failed('The AI provider was unavailable.')
            ->create();
        $this->actingAs($owner, 'web');

        // Queued: the 202 accepted shape.
        $this->withHeaders($this->headers())
            ->postJson($this->url((string) $item->public_id), ['kind' => 'summary'])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued');

        // Ready: a payload is present.
        $this->withHeaders($this->headers())
            ->getJson("/api/v1/study/artifacts/{$ready->public_id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready');

        // Failed: a reason is present and the payload is null.
        $this->withHeaders($this->headers())
            ->getJson("/api/v1/study/artifacts/{$failed->public_id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.payload', null);

        // The collection envelope, filtered and paginated.
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/study/artifacts?item_id='.$item->public_id.'&status=ready&sort=-created_at&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'study-contract-session');

        return $authenticatedRequest;
    }
}
