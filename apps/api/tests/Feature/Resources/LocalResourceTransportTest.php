<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Exercises the development-only local-disk resource transport (the stand-in
 * for S3 presigned PUT/GET). The S3-backed suite mocks the object store, so
 * these tests are the only coverage of the real local upload/download routes.
 */
final class LocalResourceTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['resources.disk' => 'local']);
        Storage::fake('local');
    }

    public function test_signed_put_stores_the_body_on_the_local_disk(): void
    {
        $key = 'resources/v1/uploads/'.str_repeat('a', 32);
        $url = URL::temporarySignedRoute(
            'resources.local-upload',
            CarbonImmutable::now()->addMinutes(10),
            ['key' => $key, 'mime' => 'text/plain', 'size' => 5],
        );

        $response = $this->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'hello');

        $response->assertNoContent();
        Storage::disk('local')->assertExists($key);
        $this->assertSame('hello', Storage::disk('local')->get($key));
    }

    public function test_put_without_a_valid_signature_is_rejected(): void
    {
        $key = 'resources/v1/uploads/'.str_repeat('a', 32);

        $response = $this->call(
            'PUT',
            '/api/v1/resource-transport/upload?key='.$key.'&mime=text/plain&size=5',
            [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'hello',
        );

        $response->assertForbidden();
    }

    public function test_put_rejects_a_key_outside_the_upload_namespace(): void
    {
        $url = URL::temporarySignedRoute(
            'resources.local-upload',
            CarbonImmutable::now()->addMinutes(10),
            ['key' => 'resources/v1/objects/'.str_repeat('a', 32), 'mime' => 'text/plain', 'size' => 5],
        );

        $this->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'hello')
            ->assertNotFound();
    }

    public function test_signed_get_streams_the_stored_object_as_an_attachment(): void
    {
        $key = 'resources/v1/objects/'.str_repeat('b', 32);
        Storage::disk('local')->put($key, 'file-body');

        $url = URL::temporarySignedRoute(
            'resources.local-download',
            CarbonImmutable::now()->addMinutes(5),
            ['key' => $key, 'mime' => 'text/markdown', 'name' => 'resource-file.md'],
        );

        $response = $this->get($url);

        $response->assertOk();
        $this->assertSame('file-body', $response->streamedContent());
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_transport_is_inert_when_the_resource_disk_is_not_local(): void
    {
        $key = 'resources/v1/uploads/'.str_repeat('a', 32);
        $url = URL::temporarySignedRoute(
            'resources.local-upload',
            CarbonImmutable::now()->addMinutes(10),
            ['key' => $key, 'mime' => 'text/plain', 'size' => 5],
        );

        config(['resources.disk' => 's3']);

        $this->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'hello')
            ->assertNotFound();
    }
}
