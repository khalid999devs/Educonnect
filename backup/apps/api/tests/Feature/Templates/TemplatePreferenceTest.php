<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\UserTemplatePreference;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Templates\Concerns\InteractsWithTemplates;
use Tests\TestCase;

final class TemplatePreferenceTest extends TestCase
{
    use InteractsWithTemplates;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_saving_and_dismissing_a_template_is_idempotent_and_private(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $template = $this->publishedTemplate();
        UserTemplatePreference::query()->forceCreate([
            'user_id' => $other->getKey(),
            'template_id' => $template->getKey(),
            'state' => 'saved',
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/templates/{$template->public_id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', false);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/templates/{$template->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', true)
            ->assertJsonPath('data.viewer_state.dismissed', false);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/templates/{$template->public_id}/saved")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', true);
        self::assertSame(1, UserTemplatePreference::query()
            ->where('user_id', $user->getKey())
            ->where('template_id', $template->getKey())
            ->count());

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/templates/{$template->public_id}/dismissed")
            ->assertOk()
            ->assertJsonPath('data.viewer_state.saved', false)
            ->assertJsonPath('data.viewer_state.dismissed', true);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/templates?preference=dismissed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $template->public_id);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/templates/{$template->public_id}/dismissed")
            ->assertNoContent();
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/templates/{$template->public_id}/dismissed")
            ->assertNoContent();

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/templates?preference=none')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        self::assertSame(0, UserTemplatePreference::query()
            ->where('user_id', $user->getKey())
            ->count());
        self::assertSame(1, UserTemplatePreference::query()
            ->where('user_id', $other->getKey())
            ->where('state', 'saved')
            ->count());
    }

    public function test_unsave_only_clears_a_saved_state_and_hidden_templates_cannot_receive_preferences(): void
    {
        $user = User::factory()->create();
        $template = $this->publishedTemplate();
        $hidden = Template::factory()->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/templates/{$template->public_id}/dismissed")
            ->assertOk();
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/templates/{$template->public_id}/saved")
            ->assertNoContent();
        self::assertSame(1, UserTemplatePreference::query()
            ->where('user_id', $user->getKey())
            ->where('state', 'dismissed')
            ->count());

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/templates/{$hidden->public_id}/saved")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }
}
