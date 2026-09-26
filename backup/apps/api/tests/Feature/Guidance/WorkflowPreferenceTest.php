<?php

declare(strict_types=1);

namespace Tests\Feature\Guidance;

use App\Domains\Guidance\Models\UserWorkflowPreference;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Models\WorkflowStep;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class WorkflowPreferenceTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_save_and_dismiss_are_exclusive_idempotent_and_private(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $workflow = $this->publishedWorkflow();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/workflows/{$workflow->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.id', $workflow->public_id)
            ->assertJsonPath('data.viewer_state.saved', true)
            ->assertJsonPath('data.viewer_state.dismissed', false);
        $saved = UserWorkflowPreference::query()->sole();
        $savedAt = $saved->updated_at;

        $this->travel(1)->minute();
        $this->withHeaders($this->headers())
            ->putJson("/api/v1/workflows/{$workflow->public_id}/saved")
            ->assertOk();
        $this->assertDatabaseCount('user_workflow_preferences', 1);
        self::assertTrue($savedAt->equalTo(UserWorkflowPreference::query()->sole()->updated_at));

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/workflows/{$workflow->public_id}/dismissed")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', false)
            ->assertJsonPath('data.viewer_state.dismissed', true);
        $this->assertDatabaseCount('user_workflow_preferences', 1);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/workflows/{$workflow->public_id}/saved")
            ->assertNoContent();
        $this->assertDatabaseHas('user_workflow_preferences', [
            'workflow_recipe_id' => $workflow->getKey(),
            'state' => 'dismissed',
        ]);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/workflows/{$workflow->public_id}/dismissed")
            ->assertNoContent();
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/workflows/{$workflow->public_id}/dismissed")
            ->assertNoContent();
        $this->assertDatabaseCount('user_workflow_preferences', 0);

        UserWorkflowPreference::factory()->create([
            'user_id' => $other->getKey(),
            'workflow_recipe_id' => $workflow->getKey(),
            'state' => 'saved',
        ]);
        $this->withHeaders($this->headers())
            ->getJson("/api/v1/workflows/{$workflow->public_id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', false)
            ->assertJsonPath('data.viewer_state.dismissed', false);
    }

    public function test_unpublished_workflows_are_concealed_from_every_preference_operation(): void
    {
        $user = User::factory()->create();
        $draft = WorkflowRecipe::factory()->create();
        $this->actingAs($user, 'web');

        foreach (['saved', 'dismissed'] as $preference) {
            foreach (['PUT', 'DELETE'] as $method) {
                $response = $this->withHeaders($this->headers())
                    ->json($method, "/api/v1/workflows/{$draft->public_id}/{$preference}");
                $this->assertApiError($response, 404, ApiErrorCode::ResourceNotFound);
            }
        }

        $this->assertDatabaseCount('user_workflow_preferences', 0);
    }

    private function publishedWorkflow(): WorkflowRecipe
    {
        $workflow = WorkflowRecipe::factory()->published()->create();
        WorkflowStep::factory()->create(['workflow_recipe_id' => $workflow->getKey()]);

        return $workflow;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'workflow-preference-test-token',
        ];
    }

    private function configureBrowserBoundary(): void
    {
        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }
}
