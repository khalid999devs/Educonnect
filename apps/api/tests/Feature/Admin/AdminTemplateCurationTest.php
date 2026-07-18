<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminTemplateCurationTest extends TestCase
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
        return (ToolCategory::query()->where('slug', 'academic-writing')->first()
            ?? ToolCategory::factory()->create(['slug' => 'academic-writing', 'name' => 'Academic writing']))->slug;
    }

    /** @return array<string, mixed> */
    private function templatePayload(): array
    {
        return [
            'category_slug' => $this->categorySlug(),
            'title' => 'Assignment Structure',
            'summary' => 'A structured outline for high-quality assignments.',
            'integrity_note' => 'A scaffold to organize your own work, not to submit as-is.',
            'provenance' => 'Curated by the learning team.',
            'format' => 'markdown',
            'body' => "# Title\n\n## Introduction\n\n## Body\n\n## Conclusion",
            'change_note' => 'Initial version.',
        ];
    }

    public function test_admin_curates_a_template_through_its_lifecycle_and_appends_versions(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($admin);

        $created = $this->adminPost('/api/v1/admin/content/templates', $this->templatePayload());
        $created->assertCreated()
            ->assertJsonPath('data.state', 'draft')
            ->assertJsonPath('data.badge', 'approved_free')
            ->assertJsonPath('data.latest_version.number', 1);
        $templateId = $created->json('data.id');

        // Submit and publish.
        $this->adminPatch("/api/v1/admin/content/templates/{$templateId}/lifecycle", [
            'transition' => 'submit_for_review',
            'expected_version' => 1,
            'reason' => 'Ready.',
        ])->assertOk()->assertJsonPath('data.state', 'in_review');

        $this->adminPatch("/api/v1/admin/content/templates/{$templateId}/lifecycle", [
            'transition' => 'publish',
            'expected_version' => 2,
            'reason' => 'Reviewed.',
        ])->assertOk()->assertJsonPath('data.state', 'published');

        // Return to draft and revise the body — a new version is appended.
        $this->adminPatch("/api/v1/admin/content/templates/{$templateId}/lifecycle", [
            'transition' => 'return_to_draft',
            'expected_version' => 3,
            'reason' => 'Revise.',
        ])->assertOk()->assertJsonPath('data.state', 'draft');

        $this->adminPut("/api/v1/admin/content/templates/{$templateId}", [
            ...$this->templatePayload(),
            'body' => "# Title\n\n## Introduction\n\n## Argument\n\n## Conclusion",
            'change_note' => 'Reworked the middle section.',
            'expected_version' => 4,
        ])->assertOk()->assertJsonPath('data.latest_version.number', 2);
    }

    public function test_a_moderator_cannot_curate_templates(): void
    {
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create([
            'email' => 'mod@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($moderator);

        $this->assertApiError($this->adminGet('/api/v1/admin/content/templates'), 403, ApiErrorCode::AuthorizationDenied);
    }
}
