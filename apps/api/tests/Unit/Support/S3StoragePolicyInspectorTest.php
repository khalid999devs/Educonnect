<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Domains\Resources\Data\StoragePolicySnapshot;
use App\Domains\Resources\Exceptions\StoragePolicyInspectionFailure;
use App\Domains\Resources\Support\S3StoragePolicyInspector;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class S3StoragePolicyInspectorTest extends TestCase
{
    public function test_non_s3_disk_fails_without_disclosing_configuration_values(): void
    {
        config()->set('resources.disk', 'policy-local');
        Storage::fake('policy-local');

        try {
            (new S3StoragePolicyInspector)->inspect();
            self::fail('The non-S3 disk was accepted.');
        } catch (StoragePolicyInspectionFailure $exception) {
            self::assertSame(
                'The resource storage disk is not an S3-compatible adapter.',
                $exception->safeMessage,
            );
            self::assertStringNotContainsString('policy-local', $exception->safeMessage);
        }
    }

    public function test_r2_reads_required_lifecycle_and_cors_apis_without_false_aws_control_calls(): void
    {
        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('getBucketLifecycleConfiguration')
            ->once()
            ->with(['Bucket' => 'private-test'])
            ->andReturn(new Result(['Rules' => []]));
        $client->shouldReceive('getBucketCors')
            ->once()
            ->with(['Bucket' => 'private-test'])
            ->andReturn(new Result(['CORSRules' => []]));
        $client->shouldNotReceive('getBucketVersioning');
        $client->shouldNotReceive('getPublicAccessBlock');
        $client->shouldNotReceive('getBucketPolicyStatus');
        $disk = $this->mockDisk(
            $client,
            'https://account.r2.cloudflarestorage.com',
        );
        Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
        $this->validConfiguration();

        $snapshot = (new S3StoragePolicyInspector)->inspect();

        $this->assertSame(StoragePolicySnapshot::PROVIDER_R2, $snapshot->provider);
        $this->assertSame('environment/resources/v1/uploads/', $snapshot->uploadPrefix);
        $this->assertSame('environment/resources/v1/objects/', $snapshot->objectPrefix);
        $this->assertNull($snapshot->versioning);
        $this->assertNull($snapshot->publicAccessBlock);
        $this->assertNull($snapshot->policyStatus);
    }

    public function test_aws_reads_versioning_and_both_public_access_apis(): void
    {
        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('getBucketLifecycleConfiguration')->once()->andReturn(new Result(['Rules' => []]));
        $client->shouldReceive('getBucketCors')->once()->andReturn(new Result(['CORSRules' => []]));
        $client->shouldReceive('getBucketVersioning')->once()->andReturn(new Result([]));
        $client->shouldReceive('getPublicAccessBlock')->once()->andReturn(new Result([]));
        $client->shouldReceive('getBucketPolicyStatus')->once()->andReturn(new Result([]));
        $disk = $this->mockDisk($client, null);
        Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
        $this->validConfiguration();

        $snapshot = (new S3StoragePolicyInspector)->inspect();

        $this->assertSame(StoragePolicySnapshot::PROVIDER_AWS, $snapshot->provider);
        $this->assertSame([], $snapshot->versioning);
        $this->assertSame([], $snapshot->publicAccessBlock);
        $this->assertSame([], $snapshot->policyStatus);
    }

    public function test_required_api_failure_is_reported_without_sdk_details(): void
    {
        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('getBucketLifecycleConfiguration')->once()->andThrow(new RuntimeException(
            'private-test https://secret.example access-key',
        ));
        $disk = $this->mockDisk($client, null);
        Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
        $this->validConfiguration();

        try {
            (new S3StoragePolicyInspector)->inspect();
            self::fail('The lifecycle API failure was accepted.');
        } catch (StoragePolicyInspectionFailure $exception) {
            $this->assertSame('The bucket lifecycle configuration could not be read.', $exception->safeMessage);

            foreach (['private-test', 'secret.example', 'access-key'] as $privateValue) {
                $this->assertStringNotContainsString($privateValue, $exception->safeMessage);
            }
        }
    }

    private function mockDisk(S3Client $client, ?string $endpoint): AwsS3V3Adapter
    {
        $disk = Mockery::mock(AwsS3V3Adapter::class);
        $disk->shouldReceive('getConfig')->once()->andReturn([
            'bucket' => 'private-test',
            'endpoint' => $endpoint,
        ]);
        $disk->shouldReceive('getClient')->once()->andReturn($client);
        $disk->shouldReceive('path')
            ->with('resources/v1/uploads/')
            ->andReturn('environment/resources/v1/uploads/');
        $disk->shouldReceive('path')
            ->with('resources/v1/objects/')
            ->andReturn('environment/resources/v1/objects/');

        return $disk;
    }

    private function validConfiguration(): void
    {
        config()->set([
            'resources.disk' => 's3',
            'resources.staging_lifecycle_max_days' => 1,
            'app.frontend_url' => 'https://web.educonnect.test',
        ]);
    }
}
