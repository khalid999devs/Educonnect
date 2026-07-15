<?php

declare(strict_types=1);

namespace Tests\Feature\Tools;

use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\UserToolPreference;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class ToolPreferenceTest extends TestCase
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
        $tool = Tool::factory()->published()->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tools/{$tool->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.id', $tool->public_id)
            ->assertJsonPath('data.viewer_state.saved', true)
            ->assertJsonPath('data.viewer_state.dismissed', false);
        $saved = UserToolPreference::query()->sole();
        $savedAt = $saved->updated_at;
        $this->assertDatabaseHas('user_tool_preferences', [
            'user_id' => $user->getKey(),
            'tool_id' => $tool->getKey(),
            'state' => 'saved',
        ]);

        $this->travel(1)->minute();
        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tools/{$tool->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', true)
            ->assertJsonPath('data.viewer_state.dismissed', false);
        $this->assertDatabaseCount('user_tool_preferences', 1);
        self::assertTrue($savedAt->equalTo(UserToolPreference::query()->sole()->updated_at));

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tools/{$tool->public_id}/dismissed")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', false)
            ->assertJsonPath('data.viewer_state.dismissed', true);
        $this->assertDatabaseCount('user_tool_preferences', 1);
        $this->assertDatabaseHas('user_tool_preferences', [
            'user_id' => $user->getKey(),
            'tool_id' => $tool->getKey(),
            'state' => 'dismissed',
        ]);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/tools/{$tool->public_id}/saved")
            ->assertNoContent();
        $this->assertDatabaseHas('user_tool_preferences', [
            'user_id' => $user->getKey(),
            'tool_id' => $tool->getKey(),
            'state' => 'dismissed',
        ]);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/tools/{$tool->public_id}/dismissed")
            ->assertNoContent();
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/tools/{$tool->public_id}/dismissed")
            ->assertNoContent();
        $this->assertDatabaseMissing('user_tool_preferences', [
            'user_id' => $user->getKey(),
            'tool_id' => $tool->getKey(),
        ]);
    }

    public function test_preferences_are_private_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $tool = Tool::factory()->published()->create();
        UserToolPreference::factory()->create([
            'user_id' => $owner->getKey(),
            'tool_id' => $tool->getKey(),
            'state' => 'saved',
        ]);
        $this->actingAs($other, 'web');

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/tools/{$tool->public_id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', false)
            ->assertJsonPath('data.viewer_state.dismissed', false);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/tools/{$tool->public_id}/dismissed")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', false)
            ->assertJsonPath('data.viewer_state.dismissed', true);

        $this->assertDatabaseCount('user_tool_preferences', 2);
        $this->assertDatabaseHas('user_tool_preferences', [
            'user_id' => $owner->getKey(),
            'tool_id' => $tool->getKey(),
            'state' => 'saved',
        ]);
        $this->assertDatabaseHas('user_tool_preferences', [
            'user_id' => $other->getKey(),
            'tool_id' => $tool->getKey(),
            'state' => 'dismissed',
        ]);
    }

    public function test_unpublished_tools_are_concealed_from_every_preference_operation(): void
    {
        $user = User::factory()->create();
        $draft = Tool::factory()->create(['state' => 'draft']);
        $this->actingAs($user, 'web');

        foreach (['saved', 'dismissed'] as $preference) {
            foreach (['PUT', 'DELETE'] as $method) {
                $response = $this->withHeaders($this->headers())
                    ->json($method, "/api/v1/tools/{$draft->public_id}/{$preference}");
                $this->assertApiError($response, 404, ApiErrorCode::ResourceNotFound);
            }
        }

        $this->assertDatabaseCount('user_tool_preferences', 0);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'tool-preference-test-token',
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
