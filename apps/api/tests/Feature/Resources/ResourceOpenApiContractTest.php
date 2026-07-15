<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Domains\Resources\Contracts\ResourceUploadSigner;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Users\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Fakes\FakeResourceUploadSigner;
use Tests\TestCase;

final class ResourceOpenApiContractTest extends TestCase
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
            'resources.disk' => 's3',
        ]);
        Storage::fake('s3');
        $this->app->instance(
            ResourceUploadSigner::class,
            new FakeResourceUploadSigner('https://objects.example.test/contract-upload'),
        );
        Storage::disk('s3')->buildTemporaryUrlsUsing(
            fn (string $path, DateTimeInterface $expiresAt, array $options): string => 'https://objects.example.test/contract-download',
        );
    }

    public function test_every_resource_operation_matches_the_live_openapi_contract(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/resources')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $link = $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/resources/links', [
                'title' => 'Contract reference',
                'description' => 'Contract-visible metadata.',
                'topic' => 'Contracts',
                'course_id' => null,
                'url' => 'https://example.edu/contracts',
            ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'link')
            ->assertJsonPath('data.version', 1);
        $linkId = $link->json('data.id');
        self::assertIsString($linkId);

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/resources/{$linkId}")
            ->assertOk()
            ->assertJsonPath('data.id', $linkId);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/resources/{$linkId}", [
                'expected_version' => 1,
                'kind' => 'link',
                'title' => 'Updated contract reference',
                'description' => null,
                'topic' => 'Contracts',
                'course_id' => null,
                'url' => 'https://example.edu/contracts/v2',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/resources?search=Updated&kind=link&topic=Contracts&file_status=all&sort=-updated_at&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $contents = "%PDF-1.7\ncontract file\n";
        $file = $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/resources/files', [
                'title' => 'Contract private file',
                'description' => null,
                'topic' => null,
                'course_id' => null,
                'original_name' => 'contract.pdf',
                'mime_type' => 'application/pdf',
                'size' => strlen($contents),
                'sha256' => hash('sha256', $contents),
            ])
            ->assertCreated()
            ->assertJsonPath('data.resource.version', 1)
            ->assertJsonPath('data.resource.file.status', 'pending')
            ->assertJsonPath('data.upload.method', 'PUT');
        $fileId = $file->json('data.resource.id');
        self::assertIsString($fileId);

        $retried = $this->withHeaders($this->mutationHeaders())
            ->postJson("/api/v1/resources/{$fileId}/upload-url", ['expected_version' => 1])
            ->assertOk()
            ->assertJsonPath('data.upload.method', 'PUT');
        $pendingVersion = $retried->json('data.resource.version');
        self::assertIsInt($pendingVersion);
        $storedFile = StoredFile::query()
            ->whereHas('resource', fn ($query) => $query->where('public_id', $fileId))
            ->firstOrFail();
        Storage::disk('s3')->put($storedFile->upload_key, $contents);

        $confirmed = $this->withHeaders($this->mutationHeaders())
            ->postJson("/api/v1/resources/{$fileId}/confirm", ['expected_version' => $pendingVersion])
            ->assertOk()
            ->assertJsonPath('data.file.status', 'ready');
        $readyVersion = $confirmed->json('data.version');
        self::assertIsInt($readyVersion);

        $this->withHeaders($this->mutationHeaders())
            ->postJson("/api/v1/resources/{$fileId}/download")
            ->assertOk()
            ->assertJsonPath('data.url', 'https://objects.example.test/contract-download');

        $cancelFile = $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/resources/files', [
                'title' => 'Cancel contract file',
                'original_name' => 'cancel.txt',
                'mime_type' => 'text/plain',
                'size' => 6,
                'sha256' => hash('sha256', 'cancel'),
            ])
            ->assertCreated();
        $cancelId = $cancelFile->json('data.resource.id');
        self::assertIsString($cancelId);

        $this->withHeaders($this->mutationHeaders())
            ->postJson("/api/v1/resources/{$cancelId}/cancel", ['expected_version' => 1])
            ->assertAccepted()
            ->assertJsonPath('data.file.status', 'deletion_pending');

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/resources/{$linkId}", ['expected_version' => 2])
            ->assertNoContent();

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/resources/{$fileId}", ['expected_version' => $readyVersion])
            ->assertAccepted()
            ->assertJsonPath('data.file.status', 'deletion_pending');

        $this->withoutRequestValidation()
            ->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/resources/links', [
                'title' => 'Invalid contract link',
                'url' => 'http://example.edu/not-private',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /** @return array<string, string> */
    private function mutationHeaders(): array
    {
        return [
            ...$this->readHeaders(),
            'X-XSRF-TOKEN' => 'resource-contract-test-token',
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
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'resource-contract-session');

        return $authenticatedRequest;
    }
}
