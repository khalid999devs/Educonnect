<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Domains\Resources\Data\StoragePolicySnapshot;
use App\Domains\Resources\Support\S3StoragePolicyInspector;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class VerifyResourceStoragePolicyCommandTest extends TestCase
{
    public function test_r2_reports_manual_controls_and_fails_closed_without_claiming_they_passed(): void
    {
        $inspector = Mockery::mock(S3StoragePolicyInspector::class);
        $inspector->shouldReceive('inspect')->once()->andReturn($this->r2Snapshot());
        $this->app->instance(S3StoragePolicyInspector::class, $inspector);

        $exit = Artisan::call('resources:verify-storage-policy');
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('PASS: Enabled lifecycle expiration', $output);
        $this->assertStringContainsString('PASS: Bucket CORS', $output);
        $this->assertStringContainsString('MANUAL: Cloudflare R2 versioning', $output);
        $this->assertStringContainsString('MANUAL: Cloudflare R2 public-bucket exposure', $output);
        $this->assertStringNotContainsString('PASS: Cloudflare R2 versioning', $output);
        $this->assertStringNotContainsString('PASS: Cloudflare R2 public-bucket exposure', $output);

        foreach (['educonnect/testing', 'resources/v1/uploads', 'resources/v1/objects'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $output);
        }
    }

    public function test_unexpected_provider_failure_is_redacted_from_command_output(): void
    {
        $inspector = Mockery::mock(S3StoragePolicyInspector::class);
        $inspector->shouldReceive('inspect')->once()->andThrow(new RuntimeException(
            'private-bucket https://secret-endpoint.example credentials signed-url',
        ));
        $this->app->instance(S3StoragePolicyInspector::class, $inspector);

        $exit = Artisan::call('resources:verify-storage-policy');
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Storage policy verification failed unexpectedly.', $output);

        foreach (['private-bucket', 'secret-endpoint', 'credentials', 'signed-url'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $output);
        }
    }

    private function r2Snapshot(): StoragePolicySnapshot
    {
        $uploadPrefix = 'educonnect/testing/resources/v1/uploads/';

        return new StoragePolicySnapshot(
            provider: StoragePolicySnapshot::PROVIDER_R2,
            maxLifecycleDays: 1,
            uploadPrefix: $uploadPrefix,
            objectPrefix: 'educonnect/testing/resources/v1/objects/',
            frontendOrigin: 'https://web.educonnect.test',
            lifecycle: ['Rules' => [[
                'Status' => 'Enabled',
                'Filter' => ['Prefix' => $uploadPrefix],
                'Expiration' => ['Days' => 1],
            ]]],
            cors: ['CORSRules' => [[
                'AllowedOrigins' => ['https://web.educonnect.test'],
                'AllowedMethods' => ['PUT'],
                'AllowedHeaders' => ['*'],
            ]]],
        );
    }
}
