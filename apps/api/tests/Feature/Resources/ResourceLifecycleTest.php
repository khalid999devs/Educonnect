<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Domains\Courses\Models\Course;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ResourceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_link_lifecycle_is_versioned_retry_safe_and_explicitly_destructive(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->getKey()]);
        $this->actingAs($user, 'web');

        $created = $this->withHeaders($this->headers())
            ->postJson('/api/v1/resources/links', [
                'title' => '  Algorithms reference  ',
                'description' => '  Private lecture companion.  ',
                'topic' => '  Graph theory  ',
                'course_id' => $course->public_id,
                'url' => '  https://example.edu/algorithms?week=4  ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'link')
            ->assertJsonPath('data.title', 'Algorithms reference')
            ->assertJsonPath('data.description', 'Private lecture companion.')
            ->assertJsonPath('data.topic', 'Graph theory')
            ->assertJsonPath('data.url', 'https://example.edu/algorithms?week=4')
            ->assertJsonPath('data.course.id', $course->public_id)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.file', null)
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.course_id')
            ->assertJsonMissingPath('data.source_url');
        $resourceId = $created->json('data.id');
        self::assertIsString($resourceId);

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/resources/{$resourceId}")
            ->assertOk()
            ->assertJsonPath('data.id', $resourceId);

        $updated = $this->withHeaders($this->headers())
            ->putJson("/api/v1/resources/{$resourceId}", [
                'expected_version' => 1,
                'kind' => 'link',
                'title' => 'Algorithms reference revised',
                'description' => null,
                'topic' => 'Graph theory',
                'course_id' => $course->public_id,
                'url' => 'https://example.edu/algorithms?week=5',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.url', 'https://example.edu/algorithms?week=5');
        $updatedAt = $updated->json('data.updated_at');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/resources/{$resourceId}", [
                'expected_version' => 1,
                'kind' => 'link',
                'title' => 'Algorithms reference revised',
                'description' => null,
                'topic' => 'Graph theory',
                'course_id' => $course->public_id,
                'url' => 'https://example.edu/algorithms?week=5',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.updated_at', $updatedAt);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/resources/{$resourceId}", [
                'expected_version' => 1,
                'kind' => 'link',
                'title' => 'Stale overwrite',
                'description' => null,
                'topic' => null,
                'course_id' => null,
                'url' => 'https://example.edu/stale',
            ])
            ->assertConflict();

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/resources/{$resourceId}", ['expected_version' => 1])
            ->assertConflict();

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/resources/{$resourceId}", ['expected_version' => 2])
            ->assertNoContent();

        $this->assertDatabaseMissing('resources', ['public_id' => $resourceId]);
    }

    public function test_resource_requests_reject_unknown_keys_unsafe_links_and_malicious_file_metadata(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/resources/links', [
                'title' => '<script>private</script>',
                'url' => 'http://user:password@example.edu/private',
                'owner_id' => 123,
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['title', 'url', 'owner_id']]]]);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/resources/files', [
                'title' => 'Unsafe upload',
                'original_name' => '../private.exe',
                'mime_type' => 'application/x-msdownload',
                'size' => 26214401,
                'sha256' => str_repeat('A', 64),
                'object_key' => 'chosen/by/client',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => [
                'original_name',
                'mime_type',
                'size',
                'sha256',
                'object_key',
            ]]]]);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/resources/files', [
                'title' => 'Mismatched extension',
                'original_name' => 'lecture-notes.txt',
                'mime_type' => 'application/pdf',
                'size' => 6,
                'sha256' => hash('sha256', '%PDF-x'),
            ])
            ->assertUnprocessable();

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?sort=created_at&unexpected=private')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['sort', 'unexpected']]]]);
    }

    public function test_file_resources_reject_link_only_url_mutation(): void
    {
        $user = User::factory()->create();
        $resource = Resource::factory()->file()->create(['user_id' => $user->getKey()]);
        StoredFile::factory()->forResource($resource)->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/resources/{$resource->public_id}", [
                'expected_version' => 1,
                'kind' => 'file',
                'title' => $resource->title,
                'description' => null,
                'topic' => null,
                'course_id' => null,
                'url' => 'https://example.edu/not-a-file-object',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('resources', [
            'id' => $resource->getKey(),
            'source_url' => null,
            'version' => 1,
        ]);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'resource-lifecycle-test-token',
        ];
    }

    private function configureBrowserBoundary(): void
    {
        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }
}
