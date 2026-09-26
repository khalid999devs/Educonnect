<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminContentOpenApiContractTest extends TestCase
{
    use InteractsWithAdminApi;
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminBoundary();
    }

    public function test_content_curation_matches_the_live_openapi_contract(): void
    {
        $superAdmin = User::factory()->withRole(RoleKey::SuperAdmin)->create([
            'email' => 'super@example.com',
            'password' => 'secret123',
        ]);
        ToolCategory::factory()->create(['slug' => 'study-planning', 'name' => 'Study planning']);
        $reviewedAt = Carbon::parse('2026-07-01T09:00:00Z');
        $publishedTool = Tool::factory()->create([
            'state' => 'published',
            'last_reviewed_at' => $reviewedAt,
            'published_at' => $reviewedAt,
        ]);

        $this->signInAsAdmin($superAdmin);

        $this->adminGet('/api/v1/admin/content/categories')->assertOk();

        $tool = $this->adminPost('/api/v1/admin/content/tools', [
            'category_slug' => 'study-planning',
            'name' => 'Concept Mapper',
            'purpose' => 'Map a reading.',
            'selection_reason' => 'Keeps sources visible.',
            'use_cases' => ['Revision'],
            'usage_guidance' => 'Paste notes.',
            'limitations' => 'Cannot judge quality.',
            'cost_note' => 'Free tier.',
            'privacy_note' => 'No PII.',
            'url' => 'https://tools.example.edu/concept-mapper',
            'provenance' => 'Reviewed.',
        ])->assertCreated();
        $toolId = $tool->json('data.id');

        $this->adminGet('/api/v1/admin/content/tools?per_page=10')->assertOk();
        $this->adminGet("/api/v1/admin/content/tools/{$toolId}")->assertOk();
        $this->adminPut("/api/v1/admin/content/tools/{$toolId}", [
            'category_slug' => 'study-planning',
            'name' => 'Concept Mapper Pro',
            'purpose' => 'Map a reading.',
            'selection_reason' => 'Keeps sources visible.',
            'use_cases' => ['Revision'],
            'usage_guidance' => 'Paste notes.',
            'limitations' => 'Cannot judge quality.',
            'cost_note' => 'Free tier.',
            'privacy_note' => 'No PII.',
            'url' => 'https://tools.example.edu/concept-mapper',
            'provenance' => 'Reviewed.',
            'expected_version' => 1,
        ])->assertOk();
        $this->adminPatch("/api/v1/admin/content/tools/{$toolId}/lifecycle", [
            'transition' => 'submit_for_review',
            'expected_version' => 2,
            'reason' => 'Ready for review.',
        ])->assertOk();

        $prompt = $this->adminPost('/api/v1/admin/content/prompts', [
            'category_slug' => 'study-planning',
            'title' => 'Plan a Study Session',
            'purpose' => 'Bounded plan.',
            'template_body' => 'Plan {{minutes}} minutes on {{topic}}.',
            'placeholders' => ['minutes', 'topic'],
            'expected_output' => 'A short plan.',
            'integrity_note' => 'Do your own study.',
            'provenance' => 'Reviewed.',
            'related_tools' => [$publishedTool->public_id],
        ])->assertCreated();
        $this->adminGet('/api/v1/admin/content/prompts?per_page=10')->assertOk();
        $this->adminPatch("/api/v1/admin/content/prompts/{$prompt->json('data.id')}/lifecycle", [
            'transition' => 'submit_for_review',
            'expected_version' => 1,
            'reason' => 'Ready.',
        ])->assertOk();

        $workflow = $this->adminPost('/api/v1/admin/content/workflows', [
            'category_slug' => 'study-planning',
            'title' => 'From Reading to Revision Notes',
            'goal' => 'Convert a chapter into notes.',
            'expected_outcome' => 'Notes with sources.',
            'integrity_note' => 'Attribute each source.',
            'provenance' => 'Reviewed.',
            'steps' => [
                ['title' => 'Read', 'instruction' => 'Mark key claims.', 'destination_action' => 'save_resource'],
            ],
        ])->assertCreated();
        $this->adminGet('/api/v1/admin/content/workflows?per_page=10')->assertOk();
        $this->adminGet("/api/v1/admin/content/workflows/{$workflow->json('data.id')}")->assertOk();
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'admin-content-contract-session');

        return $authenticatedRequest;
    }
}
