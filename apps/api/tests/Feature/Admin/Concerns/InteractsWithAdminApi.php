<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Concerns;

use App\Domains\Users\Models\User;
use Illuminate\Testing\TestResponse;

trait InteractsWithAdminApi
{
    private function configureAdminBoundary(): void
    {
        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }

    /** @return array<string, string> */
    private function adminHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
            'X-XSRF-TOKEN' => 'admin-test-token',
        ];
    }

    /**
     * Establishes a real admin session (setting the session password hash the
     * operational routes require). Only one sign-in per test - the web session
     * guard caches the first authenticated user.
     */
    private function signInAsAdmin(User $admin, string $password = 'secret123'): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/auth/login', [
                'email' => $admin->email,
                'password' => $password,
            ])
            ->assertOk();
    }

    /**
     * Establishes the short-lived step-up re-authentication grant that the
     * highest-risk admin routes (suspension, reactivation, role assignment,
     * demo-data seeding) require. Call after signInAsAdmin() in any test that
     * exercises those routes past their capability gate.
     */
    private function reauthenticateAdmin(string $password = 'secret123'): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/auth/reauth', ['password' => $password])
            ->assertOk();
    }

    private function adminGet(string $uri): TestResponse
    {
        return $this->withHeaders($this->adminHeaders())->getJson($uri);
    }

    /** @param array<string, mixed> $data */
    private function adminPost(string $uri, array $data = []): TestResponse
    {
        return $this->withHeaders($this->adminHeaders())->postJson($uri, $data);
    }

    /** @param array<string, mixed> $data */
    private function adminPatch(string $uri, array $data = []): TestResponse
    {
        return $this->withHeaders($this->adminHeaders())->patchJson($uri, $data);
    }

    /** @param array<string, mixed> $data */
    private function adminPut(string $uri, array $data = []): TestResponse
    {
        return $this->withHeaders($this->adminHeaders())->putJson($uri, $data);
    }
}
