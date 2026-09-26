<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

final class S3FilesystemTimeoutConfigurationTest extends TestCase
{
    public function test_s3_disk_passes_finite_timeout_defaults_to_the_aws_http_client(): void
    {
        $s3 = config('filesystems.disks.s3');
        $this->assertIsArray($s3);
        $this->assertSame(5.0, data_get($s3, 'http.connect_timeout'));
        $this->assertSame(60.0, data_get($s3, 'http.timeout'));
        $httpOptions = null;

        config()->set('filesystems.disks.s3', array_replace($s3, [
            'key' => 'test-access-key',
            'secret' => 'test-secret-key',
            'region' => 'auto',
            'bucket' => 'educonnect-test',
            'endpoint' => 'https://account.r2.cloudflarestorage.com',
            'http_handler' => static function (RequestInterface $request, array $options) use (&$httpOptions) {
                $httpOptions = $options;

                return Create::promiseFor(new Response(200));
            },
        ]));
        Storage::forgetDisk('s3');

        $disk = Storage::disk('s3');
        $this->assertInstanceOf(AwsS3V3Adapter::class, $disk);

        $disk->getClient()->headObject([
            'Bucket' => 'educonnect-test',
            'Key' => 'educonnect/testing/resources/v1/objects/'.str_repeat('a', 32),
        ]);

        $this->assertIsArray($httpOptions);
        $this->assertSame(5.0, $httpOptions['connect_timeout'] ?? null);
        $this->assertSame(60.0, $httpOptions['timeout'] ?? null);
    }
}
