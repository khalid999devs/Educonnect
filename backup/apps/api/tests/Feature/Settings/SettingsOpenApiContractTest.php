<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Domains\Courses\Models\Course;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

final class SettingsOpenApiContractTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }

    public function test_every_settings_operation_matches_the_live_openapi_contract(): void
    {
        $user = User::factory()->create();
        Course::factory()->create(['user_id' => $user->getKey()]);
        DB::table('sessions')->insert([
            'id' => 'contractsessionaaaaaaaaaaaaaaaaaaaaaaaaa',
            'user_id' => $user->getKey(),
            'ip_address' => '203.0.113.44',
            'user_agent' => 'EduConnect Contract Agent',
            'payload' => 'test-session-payload',
            'last_activity' => now()->getTimestamp(),
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.account.id', $user->public_id)
            ->assertJsonPath('data.course_preferences.total', 1);

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/settings/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->mutationHeaders())
            ->putJson('/api/v1/settings/account', ['name' => 'Contract Account'])
            ->assertOk()
            ->assertJsonPath('data.account.name', 'Contract Account');

        $this->withHeaders($this->mutationHeaders())
            ->putJson('/api/v1/settings/profile', [
                'institution_name' => 'Contract University',
                'institution_country_code' => 'BD',
                'department' => 'Contract Department',
                'degree' => null,
                'major' => null,
                'year_label' => 'Second year',
                'term_label' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.profile.institution_country_code', 'BD')
            ->assertJsonPath('data.profile.degree', null);
    }

    public function test_settings_validation_errors_match_the_contract(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->withoutRequestValidation()
            ->withHeaders($this->mutationHeaders())
            ->putJson('/api/v1/settings/account', ['unexpected' => true])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withoutRequestValidation()
            ->withHeaders($this->mutationHeaders())
            ->putJson('/api/v1/settings/profile', ['department' => 'Missing the rest'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /** @return array<string, string> */
    private function mutationHeaders(): array
    {
        return [
            ...$this->readHeaders(),
            'X-XSRF-TOKEN' => 'settings-contract-test-token',
        ];
    }

    /** @return array<string, string> */
    private function readHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'settings-contract-session');

        return $authenticatedRequest;
    }
}
