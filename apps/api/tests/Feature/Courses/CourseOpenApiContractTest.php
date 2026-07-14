<?php

declare(strict_types=1);

namespace Tests\Feature\Courses;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

final class CourseOpenApiContractTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

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

    public function test_every_academic_operation_matches_the_live_openapi_contract(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/academic-terms')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $term = $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/academic-terms', [
                'label' => 'Contract Term',
                'starts_on' => '2026-09-01',
                'ends_on' => null,
            ])
            ->assertCreated();
        $termId = $term->json('data.id');
        $this->assertIsString($termId);

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/academic-terms/{$termId}")
            ->assertOk()
            ->assertJsonPath('data.id', $termId);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/academic-terms/{$termId}", [
                'expected_version' => 1,
                'label' => 'Updated Contract Term',
                'starts_on' => '2026-09-01',
                'ends_on' => '2026-12-20',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/courses')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.summary.total', 0);

        $course = $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/courses', [
                'title' => 'Contract Course',
                'code' => 'CSE 4999',
                'description' => 'Contract-validated course.',
                'term_id' => $termId,
            ])
            ->assertCreated();
        $courseId = $course->json('data.id');
        $this->assertIsString($courseId);

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/courses/{$courseId}")
            ->assertOk()
            ->assertJsonPath('data.id', $courseId);

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/courses?status=all&sort=-created_at&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.summary.total', 1);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/courses/{$courseId}", [
                'expected_version' => 1,
                'title' => 'Updated Contract Course',
                'code' => null,
                'description' => null,
                'term_id' => $termId,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/courses/{$courseId}/archive", ['expected_version' => 2])
            ->assertOk()
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.status', 'archived');

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/courses/{$courseId}/archive", ['expected_version' => 3])
            ->assertOk()
            ->assertJsonPath('data.version', 4)
            ->assertJsonPath('data.status', 'active');

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/academic-terms/{$termId}", ['expected_version' => 2])
            ->assertConflict()
            ->assertJsonPath('error.code', 'CONFLICT');

        $this->withoutRequestValidation()
            ->withHeaders($this->readHeaders())
            ->getJson('/api/v1/courses?per_page=0')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/courses/{$courseId}", ['expected_version' => 4])
            ->assertNoContent();
        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/academic-terms/{$termId}", ['expected_version' => 2])
            ->assertNoContent();
    }

    /** @return array<string, string> */
    private function mutationHeaders(): array
    {
        return [
            ...$this->readHeaders(),
            'X-XSRF-TOKEN' => 'course-contract-test-token',
        ];
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
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'contract-session');

        return $authenticatedRequest;
    }
}
