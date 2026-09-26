<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminToolCurationTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithAdminApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    /** @return array<string, mixed> */
    private function toolPayload(): array
    {
        $category = ToolCategory::query()->where('slug', 'study-planning')->first()
            ?? ToolCategory::factory()->create(['slug' => 'study-planning', 'name' => 'Study planning']);

        return [
            'category_slug' => $category->slug,
            'name' => 'Concept Mapper',
            'purpose' => 'Turn a dense reading into a labelled concept map.',
            'selection_reason' => 'Chosen because it keeps sources visible and cited.',
            'use_cases' => ['Exam revision', 'Literature review'],
            'usage_guidance' => 'Paste your notes and let it group by theme.',
            'limitations' => 'It cannot judge source quality for you.',
            'cost_note' => 'Free tier is enough for coursework.',
            'privacy_note' => 'Do not paste personally identifying data.',
            'url' => 'https://tools.example.edu/concept-mapper',
            'provenance' => 'Reviewed against the provider documentation.',
        ];
    }

    public function test_admin_creates_edits_and_advances_a_tool_through_its_lifecycle(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $this->signInAsAdmin($admin);

        $created = $this->adminPost('/api/v1/admin/content/tools', $this->toolPayload());
        $created->assertCreated()
            ->assertJsonPath('data.state', 'draft')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.name', 'Concept Mapper');
        $toolId = $created->json('data.id');

        // Edit the draft.
        $edited = $this->adminPut("/api/v1/admin/content/tools/{$toolId}", [
            ...$this->toolPayload(),
            'name' => 'Concept Mapper Pro',
            'expected_version' => 1,
        ]);
        $edited->assertOk()->assertJsonPath('data.name', 'Concept Mapper Pro')
            ->assertJsonPath('data.version', 2);

        // Submit for review, then publish.
        $this->adminPatch("/api/v1/admin/content/tools/{$toolId}/lifecycle", [
            'transition' => 'submit_for_review',
            'expected_version' => 2,
            'reason' => 'Ready for review.',
        ])->assertOk()->assertJsonPath('data.state', 'in_review');

        $published = $this->adminPatch("/api/v1/admin/content/tools/{$toolId}/lifecycle", [
            'transition' => 'publish',
            'expected_version' => 3,
            'reason' => 'Reviewed and accurate.',
        ]);

        $published->assertOk()->assertJsonPath('data.state', 'published');
        $this->assertNotNull($published->json('data.published_at'));

        $this->assertDatabaseHas('audit_events', [
            'action' => AuditAction::ContentLifecycleChanged->value,
            'subject_type' => 'tool',
            'subject_id' => $toolId,
        ]);

        // A published tool cannot be edited directly.
        $this->assertApiError(
            $this->adminPut("/api/v1/admin/content/tools/{$toolId}", [
                ...$this->toolPayload(),
                'name' => 'Should fail',
                'expected_version' => 4,
            ]),
            409,
            ApiErrorCode::Conflict,
        );

        // Return to draft, then archive after republishing.
        $this->adminPatch("/api/v1/admin/content/tools/{$toolId}/lifecycle", [
            'transition' => 'return_to_draft',
            'expected_version' => 4,
            'reason' => 'Needs a wording fix.',
        ])->assertOk()->assertJsonPath('data.state', 'draft');

        $this->assertSame('draft', DB::table('tools')->where('public_id', $toolId)->value('state'));
    }

    public function test_an_invalid_transition_is_rejected(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($admin);

        $created = $this->adminPost('/api/v1/admin/content/tools', $this->toolPayload());
        $toolId = $created->json('data.id');

        // draft -> published is not a legal single step.
        $this->assertApiError(
            $this->adminPatch("/api/v1/admin/content/tools/{$toolId}/lifecycle", [
                'transition' => 'publish',
                'expected_version' => 1,
                'reason' => 'Skipping review.',
            ]),
            409,
            ApiErrorCode::Conflict,
        );
    }

    public function test_admin_lists_tools_and_categories_but_a_moderator_cannot_curate(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        Tool::factory()->create(['state' => 'draft']);

        $this->signInAsAdmin($admin);
        $this->adminGet('/api/v1/admin/content/tools')->assertOk()->assertJsonCount(1, 'data');
        $this->adminGet('/api/v1/admin/content/categories')->assertOk();
    }

    public function test_a_moderator_cannot_reach_content_curation(): void
    {
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create([
            'email' => 'mod@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($moderator);

        $this->assertApiError($this->adminGet('/api/v1/admin/content/tools'), 403, ApiErrorCode::AuthorizationDenied);
        $this->assertApiError(
            $this->adminPost('/api/v1/admin/content/tools', $this->toolPayload()),
            403,
            ApiErrorCode::AuthorizationDenied,
        );
    }
}
