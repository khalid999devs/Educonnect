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

final class AdminWorkflowCurationTest extends TestCase
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

    /** @return array<string, mixed> */
    private function workflowPayload(): array
    {
        return [
            'category_slug' => $this->categorySlug(),
            'title' => 'From Reading to Revision Notes',
            'goal' => 'Convert a chapter into revision notes with integrity.',
            'expected_outcome' => 'A set of notes in your own words with sources recorded.',
            'integrity_note' => 'Every step keeps the source attributed and reviewed.',
            'provenance' => 'Curated by the learning team.',
            'steps' => [
                ['title' => 'Read and highlight', 'instruction' => 'Mark the load-bearing claims.', 'destination_action' => 'save_resource'],
                ['title' => 'Summarize', 'instruction' => 'Write one sentence per section.', 'destination_action' => null],
            ],
        ];
    }

    public function test_admin_curates_a_workflow_through_its_lifecycle(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $this->signInAsAdmin($admin);

        $created = $this->adminPost('/api/v1/admin/content/workflows', $this->workflowPayload());
        $created->assertCreated()
            ->assertJsonPath('data.state', 'draft')
            ->assertJsonCount(2, 'data.steps');
        $workflowId = $created->json('data.id');

        $this->adminPatch("/api/v1/admin/content/workflows/{$workflowId}/lifecycle", [
            'transition' => 'submit_for_review',
            'expected_version' => 1,
            'reason' => 'Ready for review.',
        ])->assertOk()->assertJsonPath('data.state', 'in_review');

        $this->adminPatch("/api/v1/admin/content/workflows/{$workflowId}/lifecycle", [
            'transition' => 'publish',
            'expected_version' => 2,
            'reason' => 'Reviewed.',
        ])->assertOk()->assertJsonPath('data.state', 'published');

        // Return to draft to edit, changing the steps.
        $this->adminPatch("/api/v1/admin/content/workflows/{$workflowId}/lifecycle", [
            'transition' => 'return_to_draft',
            'expected_version' => 3,
            'reason' => 'Refine the steps.',
        ])->assertOk()->assertJsonPath('data.state', 'draft');

        $this->adminPut("/api/v1/admin/content/workflows/{$workflowId}", [
            ...$this->workflowPayload(),
            'steps' => [
                ['title' => 'Only step', 'instruction' => 'A single revised step.', 'destination_action' => null],
            ],
            'expected_version' => 4,
        ])->assertOk()->assertJsonCount(1, 'data.steps');
    }

    public function test_a_workflow_requires_at_least_one_step(): void
    {
        $admin = User::factory()->withRole(RoleKey::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($admin);

        $this->assertApiError(
            $this->adminPost('/api/v1/admin/content/workflows', [
                ...$this->workflowPayload(),
                'steps' => [],
            ]),
            422,
            ApiErrorCode::ValidationFailed,
        );
    }

    public function test_a_moderator_cannot_curate_workflows(): void
    {
        $moderator = User::factory()->withRole(RoleKey::Moderator)->create([
            'email' => 'mod@example.com',
            'password' => 'secret123',
        ]);
        $this->signInAsAdmin($moderator);

        $this->assertApiError($this->adminGet('/api/v1/admin/content/workflows'), 403, ApiErrorCode::AuthorizationDenied);
    }
}
