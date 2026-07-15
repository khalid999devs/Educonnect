<?php

declare(strict_types=1);

namespace Tests\Feature\Guidance;

use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\UserPromptCopy;
use App\Domains\Guidance\Models\UserPromptPreference;
use App\Domains\Tools\Models\Tool;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class PromptPreferenceTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_save_and_dismiss_are_exclusive_idempotent_desired_states(): void
    {
        $user = User::factory()->create();
        $prompt = $this->publishedPrompt();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/prompts/{$prompt->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.id', $prompt->public_id)
            ->assertJsonPath('data.viewer_state.saved', true)
            ->assertJsonPath('data.viewer_state.dismissed', false);
        $saved = UserPromptPreference::query()->sole();
        $savedAt = $saved->updated_at;

        $this->travel(1)->minute();
        $this->withHeaders($this->headers())
            ->putJson("/api/v1/prompts/{$prompt->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', true);
        $this->assertDatabaseCount('user_prompt_preferences', 1);
        self::assertTrue($savedAt->equalTo(UserPromptPreference::query()->sole()->updated_at));

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/prompts/{$prompt->public_id}/dismissed")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', false)
            ->assertJsonPath('data.viewer_state.dismissed', true);
        $this->assertDatabaseCount('user_prompt_preferences', 1);
        $this->assertDatabaseHas('user_prompt_preferences', [
            'user_id' => $user->getKey(),
            'prompt_template_id' => $prompt->getKey(),
            'state' => 'dismissed',
        ]);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/prompts/{$prompt->public_id}/saved")
            ->assertNoContent();
        $this->assertDatabaseHas('user_prompt_preferences', [
            'prompt_template_id' => $prompt->getKey(),
            'state' => 'dismissed',
        ]);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/prompts/{$prompt->public_id}/dismissed")
            ->assertNoContent();
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/prompts/{$prompt->public_id}/dismissed")
            ->assertNoContent();
        $this->assertDatabaseCount('user_prompt_preferences', 0);
    }

    public function test_copy_tracking_is_private_bounded_and_survives_preference_changes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $prompt = $this->publishedPrompt();
        UserPromptCopy::factory()->create([
            'user_id' => $other->getKey(),
            'prompt_template_id' => $prompt->getKey(),
            'copy_count' => 5,
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/prompts/{$prompt->public_id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.copy_count', 0);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/prompts/{$prompt->public_id}/copies")
            ->assertOk()
            ->assertJsonPath('data.id', $prompt->public_id)
            ->assertJsonPath('data.viewer_state.copy_count', 1);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/prompts/{$prompt->public_id}/copies")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.copy_count', 2);

        $copy = UserPromptCopy::query()->where('user_id', $user->getKey())->sole();
        self::assertSame(2, $copy->copy_count);
        self::assertTrue($copy->first_copied_at->lessThanOrEqualTo($copy->last_copied_at));

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/prompts/{$prompt->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.copy_count', 2);
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/prompts/{$prompt->public_id}/saved")
            ->assertNoContent();
        $this->assertDatabaseCount('user_prompt_copies', 2);
        $this->assertDatabaseHas('user_prompt_copies', [
            'user_id' => $other->getKey(),
            'copy_count' => 5,
        ]);
    }

    public function test_preferences_are_private_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $prompt = $this->publishedPrompt();
        UserPromptPreference::factory()->create([
            'user_id' => $owner->getKey(),
            'prompt_template_id' => $prompt->getKey(),
            'state' => 'saved',
        ]);
        $this->actingAs($other, 'web');

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/prompts/{$prompt->public_id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', false)
            ->assertJsonPath('data.viewer_state.dismissed', false);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/prompts/{$prompt->public_id}/dismissed")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.dismissed', true);

        $this->assertDatabaseCount('user_prompt_preferences', 2);
        $this->assertDatabaseHas('user_prompt_preferences', [
            'user_id' => $owner->getKey(),
            'state' => 'saved',
        ]);
    }

    public function test_unpublished_prompts_are_concealed_from_every_preference_and_copy_operation(): void
    {
        $user = User::factory()->create();
        $draft = PromptTemplate::factory()->create();
        $this->actingAs($user, 'web');

        foreach (['saved', 'dismissed'] as $preference) {
            foreach (['PUT', 'DELETE'] as $method) {
                $response = $this->withHeaders($this->headers())
                    ->json($method, "/api/v1/prompts/{$draft->public_id}/{$preference}");
                $this->assertApiError($response, 404, ApiErrorCode::ResourceNotFound);
            }
        }

        $copyResponse = $this->withHeaders($this->headers())
            ->postJson("/api/v1/prompts/{$draft->public_id}/copies");
        $this->assertApiError($copyResponse, 404, ApiErrorCode::ResourceNotFound);

        $this->assertDatabaseCount('user_prompt_preferences', 0);
        $this->assertDatabaseCount('user_prompt_copies', 0);
    }

    private function publishedPrompt(): PromptTemplate
    {
        $prompt = PromptTemplate::factory()->published()->create();
        $prompt->relatedTools()->attach(Tool::factory()->published()->create()->getKey());

        return $prompt;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'prompt-preference-test-token',
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
