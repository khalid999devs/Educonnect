<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminPromptCurationTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithAdminApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    private function categorySlug(): string
    {
        return (ToolCategory::query()->where('slug', 'study-planning')->first()
            ?? ToolCategory::factory()->create(['slug' => 'study-planning', 'name' => 'Study planning']))->slug;
    }

    private function publishedTool(): Tool
    {
        $reviewedAt = Carbon::parse('2026-07-01T09:00:00Z');

        return Tool::factory()->create([
            'state' => 'published',
            'last_reviewed_at' => $reviewedAt,
            'published_at' => $reviewedAt,
        ]);
    }

    /**
     * @param  list<string>  $relatedToolIds
     * @return array<string, mixed>
     */
    private function promptPayload(array $relatedToolIds): array
    {
        return [
            'category_slug' => $this->categorySlug(),
            'title' => 'Plan a Study Session',
            'purpose' => 'Turn a vague study goal into a bounded plan.',
            'template_body' => 'Help me plan a {{minutes}}-minute session on {{topic}}.',
            'placeholders' => ['minutes', 'topic'],
            'expected_output' => 'A short ordered plan with time estimates.',
            'integrity_note' => 'Use the plan to organize your own study.',
            'provenance' => 'Drafted and reviewed by the learning team.',
            'related_tools' => $relatedToolIds,
        ];
    }

    public function test_admin_curates_a_prompt_through_its_lifecycle(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $tool = $this->publishedTool();

        $this->signInAsAdmin($admin);

        $created = $this->adminPost('/api/v1/admin/content/prompts', $this->promptPayload([$tool->public_id]));
        $created->assertCreated()->assertJsonPath('data.state', 'draft');
        $promptId = $created->json('data.id');

        $this->adminPatch("/api/v1/admin/content/prompts/{$promptId}/lifecycle", [
            'transition' => 'submit_for_review',
            'expected_version' => 1,
            'reason' => 'Ready for review.',
        ])->assertOk()->assertJsonPath('data.state', 'in_review');

        $this->adminPatch("/api/v1/admin/content/prompts/{$promptId}/lifecycle", [
            'transition' => 'publish',
            'expected_version' => 2,
            'reason' => 'Reviewed and accurate.',
        ])->assertOk()->assertJsonPath('data.state', 'published');
    }

    public function test_publishing_a_prompt_without_a_related_tool_is_rejected(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $this->signInAsAdmin($admin);

        $created = $this->adminPost('/api/v1/admin/content/prompts', $this->promptPayload([]));
        $promptId = $created->json('data.id');

        $this->adminPatch("/api/v1/admin/content/prompts/{$promptId}/lifecycle", [
            'transition' => 'submit_for_review',
            'expected_version' => 1,
            'reason' => 'Ready.',
        ])->assertOk();

        $this->assertApiError(
            $this->adminPatch("/api/v1/admin/content/prompts/{$promptId}/lifecycle", [
                'transition' => 'publish',
                'expected_version' => 2,
                'reason' => 'Publishing without a related tool.',
            ]),
            409,
            ApiErrorCode::Conflict,
        );
    }

    public function test_a_moderator_cannot_curate_prompts(): void
    {
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create([
            'email' => 'mod@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($moderator);

        $this->assertApiError($this->adminGet('/api/v1/admin/content/prompts'), 403, ApiErrorCode::AuthorizationDenied);
    }
}
