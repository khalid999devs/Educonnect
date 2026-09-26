<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Domains\Courses\Models\Course;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Feature\Templates\Concerns\InteractsWithTemplates;
use Tests\TestCase;

final class TemplateOpenApiContractTest extends TestCase
{
    use InteractsWithTemplates;
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_every_student_template_operation_matches_the_live_openapi_contract(): void
    {
        $user = User::factory()->create();
        $template = $this->publishedTemplate(['title' => 'Contract Study Template']);
        $hidden = Template::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/templates?search=Contract&preference=all&sort=title&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $template->public_id);

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/templates/{$template->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $template->public_id);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/templates/{$template->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', true);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/templates/{$template->public_id}/saved")
            ->assertNoContent();

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/templates/{$template->public_id}/dismissed")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.dismissed', true);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/templates/{$template->public_id}/dismissed")
            ->assertNoContent();

        $copyId = $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", [
                'destination' => 'course',
                'course_id' => $course->public_id,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", [
                'destination' => 'course',
                'course_id' => $course->public_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $copyId);

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/template-copies?destination=course&include_archived=0&sort=-created_at&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/template-copies/{$copyId}")
            ->assertOk()
            ->assertJsonPath('data.id', $copyId);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/template-copies/{$copyId}", [
                'expected_version' => 1,
                'title' => 'Contract Adjusted Copy',
                'body' => 'Adjusted body for the contract test.',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/template-copies/{$copyId}/archive", ['expected_version' => 2])
            ->assertOk();

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/template-copies/{$copyId}/archive", ['expected_version' => 3])
            ->assertOk()
            ->assertJsonPath('data.archived_at', null);

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/templates/{$hidden->public_id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->withoutRequestValidation()
            ->withHeaders($this->readHeaders())
            ->getJson('/api/v1/templates?preference=unsupported&per_page=51')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withoutRequestValidation()
            ->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", ['destination' => 'somewhere'])
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
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'template-contract-session');

        return $authenticatedRequest;
    }
}
