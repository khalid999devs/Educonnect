<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Domains\Resources\Contracts\ResourceUploadSigner;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\AssertsApiResponses;
use Tests\Fakes\FakeResourceUploadSigner;
use Tests\TestCase;

final class ResourceStorageTest extends TestCase
{
    use AssertsApiResponses;
    use RefreshDatabase;

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
        $this->app->instance(ResourceUploadSigner::class, new FakeResourceUploadSigner);
    }

    public function test_private_file_upload_retry_confirm_download_and_delete_lifecycle(): void
    {
        $this->configureTemporaryUrls();
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $contents = "%PDF-1.7\nprivate lecture notes\n";

        $initiated = $this->withHeaders($this->headers())
            ->postJson('/api/v1/resources/files', [
                'title' => 'Private lecture notes',
                'description' => null,
                'topic' => 'Week 4',
                'course_id' => null,
                'original_name' => 'lecture-notes.pdf',
                'mime_type' => 'application/pdf',
                'size' => strlen($contents),
                'sha256' => hash('sha256', $contents),
            ])
            ->assertCreated()
            ->assertJsonPath('data.resource.kind', 'file')
            ->assertJsonPath('data.resource.version', 1)
            ->assertJsonPath('data.resource.url', null)
            ->assertJsonPath('data.resource.file.status', 'pending')
            ->assertJsonPath('data.resource.file.original_name', 'lecture-notes.pdf')
            ->assertJsonPath('data.upload.method', 'PUT')
            ->assertJsonPath('data.upload.url', 'https://objects.example.test/private-upload')
            ->assertJsonPath('data.upload.headers.Content-Type', 'application/pdf')
            ->assertJsonMissingPath('data.resource.file.sha256')
            ->assertJsonMissingPath('data.resource.file.upload_key')
            ->assertJsonMissingPath('data.resource.file.object_key')
            ->assertJsonMissingPath('data.resource.file.disk');
        $resourceId = $initiated->json('data.resource.id');
        self::assertIsString($resourceId);

        $retried = $this->withHeaders($this->headers())
            ->postJson("/api/v1/resources/{$resourceId}/upload-url", ['expected_version' => 1])
            ->assertOk()
            ->assertJsonPath('data.resource.file.status', 'pending')
            ->assertJsonPath('data.upload.method', 'PUT');
        $retryVersion = $retried->json('data.resource.version');
        self::assertIsInt($retryVersion);

        $file = StoredFile::query()
            ->whereHas('resource', fn ($query) => $query->where('public_id', $resourceId))
            ->firstOrFail();
        Storage::disk('s3')->put($file->upload_key, $contents);

        $confirmed = $this->withHeaders($this->headers())
            ->postJson("/api/v1/resources/{$resourceId}/confirm", ['expected_version' => $retryVersion])
            ->assertOk()
            ->assertJsonPath('data.file.status', 'ready')
            ->assertJsonPath('data.file.verified_mime_type', 'application/pdf')
            ->assertJsonPath('data.file.verified_size', strlen($contents));
        $readyVersion = $confirmed->json('data.version');
        self::assertIsInt($readyVersion);

        $file->refresh();
        $stagingKey = $file->upload_key;
        self::assertIsString($stagingKey);
        self::assertNotNull($file->cleanup_after);
        Storage::disk('s3')->assertExists($stagingKey);
        Storage::disk('s3')->assertExists($file->object_key);

        Storage::disk('s3')->put($stagingKey, "%PDF-1.7\nlate signed PUT\n");
        self::assertSame($contents, Storage::disk('s3')->get($file->object_key));

        $this->travelTo(CarbonImmutable::instance($file->cleanup_after)->addSecond());
        $this->artisan('resources:reconcile-storage', ['--limit' => 100])->assertSuccessful();
        $file->refresh();
        self::assertSame($stagingKey, $file->upload_key);
        self::assertNotNull($file->cleanup_started_at);
        self::assertNotNull($file->cleanup_after);
        Storage::disk('s3')->assertMissing($stagingKey);
        Storage::disk('s3')->assertExists($file->object_key);
        self::assertSame($contents, Storage::disk('s3')->get($file->object_key));

        Storage::disk('s3')->put($stagingKey, "%PDF-1.7\nrequest completed after first cleanup\n");
        $this->travelTo(CarbonImmutable::instance($file->cleanup_after)->addSecond());
        $this->artisan('resources:reconcile-storage', ['--limit' => 100])->assertSuccessful();
        $file->refresh();
        self::assertNull($file->upload_key);
        self::assertNull($file->cleanup_after);
        self::assertNull($file->cleanup_started_at);
        Storage::disk('s3')->assertMissing($stagingKey);
        Storage::disk('s3')->assertExists($file->object_key);
        self::assertSame($contents, Storage::disk('s3')->get($file->object_key));

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/resources/{$resourceId}/download")
            ->assertOk()
            ->assertJsonPath('data.url', 'https://objects.example.test/private-download')
            ->assertJsonStructure(['data' => ['url', 'expires_at'], 'meta' => ['request_id']])
            ->assertJsonMissingPath('data.object_key')
            ->assertJsonMissingPath('data.disk');

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/resources/{$resourceId}", ['expected_version' => $readyVersion])
            ->assertAccepted()
            ->assertJsonPath('data.file.status', 'deletion_pending');

        $this->artisan('resources:reconcile-storage')->assertSuccessful();
        $this->assertDatabaseMissing('resources', ['public_id' => $resourceId]);
        Storage::disk('s3')->assertMissing($file->object_key);
    }

    public function test_confirm_rejects_content_that_does_not_match_declared_file_metadata(): void
    {
        $this->configureTemporaryUrls();
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $contents = 'This is not a PDF.';

        $initiated = $this->withHeaders($this->headers())
            ->postJson('/api/v1/resources/files', [
                'title' => 'Spoofed PDF',
                'original_name' => 'spoofed.pdf',
                'mime_type' => 'application/pdf',
                'size' => strlen($contents),
                'sha256' => hash('sha256', $contents),
            ])
            ->assertCreated();
        $resourceId = $initiated->json('data.resource.id');
        self::assertIsString($resourceId);
        $file = StoredFile::query()
            ->whereHas('resource', fn ($query) => $query->where('public_id', $resourceId))
            ->firstOrFail();
        Storage::disk('s3')->put($file->upload_key, $contents);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/resources/{$resourceId}/confirm", ['expected_version' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertDatabaseHas('stored_files', [
            'id' => $file->getKey(),
            'status' => 'pending',
            'verified_mime_type' => null,
            'verified_size' => null,
        ]);
        Storage::disk('s3')->assertExists($file->object_key);

        $this->travelTo(CarbonImmutable::instance($file->cleanup_after)->addSecond());
        $this->artisan('resources:reconcile-storage')->assertSuccessful();
        $file->refresh();
        self::assertSame('deletion_pending', $file->status->value);
        self::assertNotNull($file->cleanup_started_at);
        $this->travelTo(CarbonImmutable::instance($file->cleanup_after)->addSecond());
        $this->artisan('resources:reconcile-storage')->assertSuccessful();
        $this->assertDatabaseMissing('resources', ['public_id' => $resourceId]);
        Storage::disk('s3')->assertMissing($file->upload_key);
        Storage::disk('s3')->assertMissing($file->object_key);
    }

    public function test_confirm_rejects_missing_staging_objects_and_invalid_text_bytes(): void
    {
        $this->configureTemporaryUrls();
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $missing = Resource::factory()->file()->create(['user_id' => $user->getKey()]);
        StoredFile::factory()->forResource($missing)->create();

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/resources/{$missing->public_id}/confirm", ['expected_version' => 1])
            ->assertConflict();

        $contents = "valid-prefix\0invalid-text";
        $initiated = $this->withHeaders($this->headers())
            ->postJson('/api/v1/resources/files', [
                'title' => 'Invalid text',
                'original_name' => 'invalid.txt',
                'mime_type' => 'text/plain',
                'size' => strlen($contents),
                'sha256' => hash('sha256', $contents),
            ])
            ->assertCreated();
        $resourceId = $initiated->json('data.resource.id');
        self::assertIsString($resourceId);
        $file = StoredFile::query()
            ->whereHas('resource', fn ($query) => $query->where('public_id', $resourceId))
            ->firstOrFail();
        Storage::disk('s3')->put($file->upload_key, $contents);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/resources/{$resourceId}/confirm", ['expected_version' => 1])
            ->assertUnprocessable();

        $this->assertDatabaseHas('stored_files', [
            'id' => $file->getKey(),
            'status' => 'pending',
            'verified_mime_type' => null,
        ]);
    }

    public function test_cancel_marks_pending_file_for_cleanup_without_exposing_private_storage_state(): void
    {
        $this->configureTemporaryUrls();
        $user = User::factory()->create();
        $resource = Resource::factory()->file()->create(['user_id' => $user->getKey()]);
        $file = StoredFile::factory()->forResource($resource)->create();
        Storage::disk('s3')->put($file->upload_key, 'partial upload');
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/resources/{$resource->public_id}/cancel", ['expected_version' => 1])
            ->assertAccepted()
            ->assertJsonPath('data.file.status', 'deletion_pending')
            ->assertJsonMissingPath('data.file.upload_key')
            ->assertJsonMissingPath('data.file.object_key');

        $file->refresh();
        $resource->refresh();
        self::assertNotNull($file->upload_key);
        self::assertNotNull($file->cleanup_after);
        self::assertSame('deletion_pending', $file->status->value);
        self::assertSame(2, $resource->version);
    }

    public function test_reconciliation_is_bounded_and_removes_expired_pending_and_deletion_tombstones(): void
    {
        $firstResource = Resource::factory()->file()->create();
        $firstFile = StoredFile::factory()->forResource($firstResource)->create([
            'upload_expires_at' => now()->addMinute(),
            'cleanup_after' => now()->addMinutes(2),
        ]);
        $secondResource = Resource::factory()->file()->create();
        $secondFile = StoredFile::factory()->forResource($secondResource)->deletionPending(false)->create([
            'upload_expires_at' => now()->addMinute(),
            'cleanup_after' => now()->addMinutes(2),
        ]);
        Storage::disk('s3')->put($firstFile->upload_key, 'abandoned upload');
        Storage::disk('s3')->put($secondFile->upload_key, 'cancelled staging object');
        Storage::disk('s3')->put($secondFile->object_key, 'cancelled final object');
        $this->travel(3)->minutes();

        $this->artisan('resources:reconcile-storage', ['--limit' => 1])->assertSuccessful();
        self::assertSame(1, StoredFile::query()
            ->whereIn('id', [$firstFile->getKey(), $secondFile->getKey()])
            ->whereNotNull('cleanup_started_at')
            ->count());

        $this->artisan('resources:reconcile-storage', ['--limit' => 10])->assertSuccessful();
        self::assertSame(2, Resource::query()
            ->whereIn('id', [$firstResource->getKey(), $secondResource->getKey()])
            ->count());
        self::assertSame(2, StoredFile::query()
            ->whereIn('id', [$firstFile->getKey(), $secondFile->getKey()])
            ->whereNotNull('cleanup_started_at')
            ->count());

        $finalCleanup = StoredFile::query()
            ->whereIn('id', [$firstFile->getKey(), $secondFile->getKey()])
            ->max('cleanup_after');
        self::assertNotNull($finalCleanup);
        $this->travelTo(CarbonImmutable::parse((string) $finalCleanup)->addSecond());
        $this->artisan('resources:reconcile-storage', ['--limit' => 1])->assertSuccessful();
        self::assertSame(1, Resource::query()
            ->whereIn('id', [$firstResource->getKey(), $secondResource->getKey()])
            ->count());
        $this->artisan('resources:reconcile-storage', ['--limit' => 10])->assertSuccessful();
        $this->assertDatabaseMissing('resources', ['id' => $firstResource->getKey()]);
        $this->assertDatabaseMissing('resources', ['id' => $secondResource->getKey()]);
        $this->assertDatabaseMissing('stored_files', ['id' => $firstFile->getKey()]);
        $this->assertDatabaseMissing('stored_files', ['id' => $secondFile->getKey()]);
        Storage::disk('s3')->assertMissing($firstFile->upload_key);
        Storage::disk('s3')->assertMissing($secondFile->upload_key);
        Storage::disk('s3')->assertMissing($secondFile->object_key);
    }

    public function test_reconciliation_keeps_database_tombstone_when_storage_cleanup_fails_for_retry(): void
    {
        $resource = Resource::factory()->file()->create();
        $file = StoredFile::factory()->forResource($resource)->deletionPending(false)->create([
            'upload_expires_at' => now()->addMinute(),
            'cleanup_after' => now()->addMinutes(2),
        ]);
        $this->travel(3)->minutes();
        Storage::shouldReceive('disk')
            ->once()
            ->andThrow(new RuntimeException(
                'failed https://objects.example.test/private-token/'.$file->object_key,
            ));

        $this->artisan('resources:reconcile-storage', ['--limit' => 10])->assertFailed();

        $this->assertDatabaseHas('resources', ['id' => $resource->getKey()]);
        $this->assertDatabaseHas('stored_files', [
            'id' => $file->getKey(),
            'status' => 'deletion_pending',
            'upload_key' => $file->upload_key,
            'object_key' => $file->object_key,
            'cleanup_failures' => 1,
        ]);
        $file->refresh();
        self::assertTrue($file->cleanup_after->isFuture());
    }

    public function test_cleanup_backoff_never_extends_an_expired_upload_confirmation_window(): void
    {
        $user = User::factory()->create();
        $resource = Resource::factory()->file()->create(['user_id' => $user->getKey()]);
        $contents = "%PDF-1.7\nexpired grant\n";
        $file = StoredFile::factory()->forResource($resource)->create([
            'expected_size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'upload_expires_at' => now()->addMinute(),
            'cleanup_after' => now()->addMinutes(2),
        ]);
        Storage::disk('s3')->put($file->upload_key, $contents);
        $storageManager = Storage::getFacadeRoot();
        $this->travel(3)->minutes();

        try {
            Storage::shouldReceive('disk')
                ->once()
                ->andThrow(new RuntimeException('provider cleanup timeout'));

            $this->artisan('resources:reconcile-storage', ['--limit' => 10])->assertFailed();
        } finally {
            Storage::swap($storageManager);
        }

        $file->refresh();
        self::assertTrue($file->cleanup_after->isFuture());
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/resources/{$resource->public_id}/confirm", ['expected_version' => 1])
            ->assertConflict();

        $this->assertDatabaseHas('stored_files', [
            'id' => $file->getKey(),
            'status' => 'pending',
            'cleanup_failures' => 1,
        ]);
    }

    public function test_storage_failures_return_a_generic_error_and_do_not_log_urls_keys_or_private_metadata(): void
    {
        $this->app->instance(
            ResourceUploadSigner::class,
            new FakeResourceUploadSigner(failure: new RuntimeException(
                'provider failed for https://objects.example.test/signed-secret at resources/v1/uploads/private-key',
            )),
        );
        Log::spy();
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/resources/files', [
                'title' => 'Private failure title',
                'original_name' => 'failure.pdf',
                'mime_type' => 'application/pdf',
                'size' => 6,
                'sha256' => hash('sha256', '%PDF-x'),
            ]);
        $this->assertApiError($response, 503, ApiErrorCode::ServiceUnavailable);
        self::assertStringNotContainsString(
            'objects.example.test',
            (string) $response->getContent(),
        );

        Log::shouldHaveReceived('error')->withArgs(
            static function (string $message, array $context): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'Resource storage operation failed.'
                    && ($context['operation'] ?? null) === 'upload.sign'
                    && ! str_contains($encoded, 'objects.example.test')
                    && ! str_contains($encoded, 'Private failure title')
                    && ! str_contains($encoded, 'failure.pdf')
                    && ! str_contains($encoded, 'resources/v1/');
            },
        )->once();
        $this->assertDatabaseHas('resources', [
            'user_id' => $user->getKey(),
            'title' => 'Private failure title',
            'kind' => 'file',
            'version' => 2,
        ]);
        $this->assertDatabaseHas('stored_files', [
            'user_id' => $user->getKey(),
            'original_name' => 'failure.pdf',
            'status' => 'deletion_pending',
        ]);
    }

    private function configureTemporaryUrls(): void
    {
        Storage::disk('s3')->buildTemporaryUrlsUsing(
            fn (string $path, DateTimeInterface $expiresAt, array $options): string => 'https://objects.example.test/private-download',
        );
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'resource-storage-test-token',
        ];
    }
}
