<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Feature\Admin\Concerns\InteractsWithAdminApi;
use Tests\TestCase;

final class AdminExtendedContractTest extends TestCase
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

    public function test_templates_communities_analytics_and_demo_match_the_contract(): void
    {
        $superAdmin = User::factory()->withRole(RoleKey::SuperAdmin)->create([
            'email' => 'super@example.com',
            'password' => 'secret123',
        ]);
        ToolCategory::factory()->create(['slug' => 'academic-writing', 'name' => 'Academic writing']);

        $this->signInAsAdmin($superAdmin);
        $this->reauthenticateAdmin();

        // Templates.
        $template = $this->adminPost('/api/v1/admin/content/templates', [
            'category_slug' => 'academic-writing',
            'title' => 'Assignment Structure',
            'summary' => 'A structured outline.',
            'integrity_note' => 'A scaffold, not a submission.',
            'provenance' => 'Reviewed.',
            'format' => 'markdown',
            'body' => "# Title\n\n## Body",
            'change_note' => 'Initial.',
        ])->assertCreated();
        $templateId = $template->json('data.id');
        $this->adminGet('/api/v1/admin/content/templates?per_page=10')->assertOk();
        $this->adminGet("/api/v1/admin/content/templates/{$templateId}")->assertOk();
        $this->adminPut("/api/v1/admin/content/templates/{$templateId}", [
            'category_slug' => 'academic-writing',
            'title' => 'Assignment Structure v2',
            'summary' => 'A structured outline.',
            'integrity_note' => 'A scaffold, not a submission.',
            'provenance' => 'Reviewed.',
            'expected_version' => 1,
        ])->assertOk();
        $this->adminPatch("/api/v1/admin/content/templates/{$templateId}/lifecycle", [
            'transition' => 'submit_for_review',
            'expected_version' => 2,
            'reason' => 'Ready.',
        ])->assertOk();

        // Communities.
        $community = $this->adminPost('/api/v1/admin/communities', [
            'name' => 'Thesis Writers',
            'summary' => 'Support for thesis writers.',
            'description' => 'Share drafts and feedback.',
            'topic' => 'Academic writing',
        ])->assertCreated();
        $communityId = $community->json('data.id');
        $this->adminGet('/api/v1/admin/communities?per_page=10')->assertOk();
        $this->adminPut("/api/v1/admin/communities/{$communityId}", [
            'name' => 'Thesis Writers Circle',
            'summary' => 'Support for thesis writers.',
            'expected_version' => 1,
        ])->assertOk();
        $this->adminPatch("/api/v1/admin/communities/{$communityId}/visibility", [
            'visibility' => 'archived',
            'expected_version' => 2,
            'reason' => 'Retiring.',
        ])->assertOk();

        // Analytics + telemetry + demo data.
        $this->adminGet('/api/v1/admin/analytics')->assertOk();
        $this->adminGet('/api/v1/admin/telemetry')->assertOk();
        $this->adminPost('/api/v1/admin/demo-data', ['reason' => 'Seed the catalog.'])->assertOk();
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'admin-extended-contract-session');

        return $authenticatedRequest;
    }
}
