<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Tests\TestCase;

final class AuthorizationOpenApiContractTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sanctum.stateful', ['localhost:3000', 'localhost:3001']);
        config()->set('cors.allowed_origins', [
            'http://localhost:3000',
            'http://localhost:3001',
        ]);
    }

    public function test_admin_login_and_current_administrator_match_the_openapi_contract(): void
    {
        $administrator = User::factory()->create([
            'email' => 'admin-contract@example.com',
            'password' => 'secret123',
        ]);
        $this->assignRole($administrator, 'admin');

        $loginResponse = $this->withHeaders($this->adminMutationHeaders())
            ->postJson('/api/v1/admin/auth/login', [
                'email' => $administrator->email,
                'password' => 'secret123',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.primary_role', 'admin')
            ->assertJsonPath('data.authorization.roles.0', 'admin');

        $this->assertContains('admin.access', $loginResponse->json('data.authorization.capabilities'));

        $currentAdminResponse = $this->withoutRequestValidation()
            ->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $administrator->public_id)
            ->assertJsonPath('data.user.primary_role', 'admin');

        $this->assertContains('admin.access', $currentAdminResponse->json('data.authorization.capabilities'));
    }

    public function test_admin_logout_matches_the_openapi_contract(): void
    {
        $administrator = User::factory()->create([
            'email' => 'logout-admin-contract@example.com',
            'password' => 'secret123',
        ]);
        $this->assignRole($administrator, 'admin');

        $this->withHeaders($this->adminMutationHeaders())
            ->postJson('/api/v1/admin/auth/login', [
                'email' => $administrator->email,
                'password' => 'secret123',
            ])
            ->assertOk();

        $this->withoutRequestValidation()
            ->withHeaders($this->adminMutationHeaders())
            ->postJson('/api/v1/admin/auth/logout')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_admin_denial_responses_match_the_openapi_contract(): void
    {
        $this->withoutRequestValidation()
            ->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/me')
            ->assertUnauthorized();

        $student = User::factory()->create([
            'email' => 'student-contract@example.com',
            'password' => 'secret123',
        ]);

        $this->withHeaders($this->adminMutationHeaders())
            ->postJson('/api/v1/admin/auth/login', [
                'email' => $student->email,
                'password' => 'secret123',
            ])
            ->assertUnprocessable();
    }

    private function assignRole(User $user, string $roleKey): void
    {
        $roleId = DB::table('roles')->where('key', $roleKey)->value('id');

        $this->assertIsInt($roleId);

        DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $user->getKey(),
            'assigned_at' => now(),
        ]);

        $user->unsetRelation('roles');
    }

    /**
     * @return array<string, string>
     */
    private function adminMutationHeaders(): array
    {
        return [
            ...$this->adminHeaders(),
            'X-XSRF-TOKEN' => 'contract-test-token',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function adminHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ];
    }
}
