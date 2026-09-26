<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Domains\Resources\Support\S3StrictPutUploadSigner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class S3StrictPutUploadSignerTest extends TestCase
{
    public function test_real_aws_sdk_grant_signs_exact_content_type_and_browser_managed_length(): void
    {
        config()->set('resources.disk', 's3');
        config()->set('filesystems.disks.s3', [
            'driver' => 's3',
            'key' => 'test-access-key',
            'secret' => 'test-secret-key',
            'region' => 'auto',
            'bucket' => 'educonnect-test',
            'endpoint' => 'https://account.r2.cloudflarestorage.com',
            'root' => 'educonnect/testing',
            'use_path_style_endpoint' => true,
            'http' => [
                'connect_timeout' => 5.0,
                'timeout' => 60.0,
            ],
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
        ]);
        Storage::forgetDisk('s3');

        $grant = (new S3StrictPutUploadSigner)->sign(
            'resources/v1/uploads/'.str_repeat('a', 32),
            CarbonImmutable::now()->addMinutes(10),
            'application/pdf',
            12_345,
        );

        parse_str((string) parse_url($grant->url, PHP_URL_QUERY), $query);
        $signedHeaders = $query['X-Amz-SignedHeaders'] ?? null;
        $this->assertIsString($signedHeaders);
        $this->assertSame(
            ['content-length', 'content-type', 'host'],
            explode(';', $signedHeaders),
        );
        $this->assertSame(['Content-Type' => 'application/pdf'], $grant->headers);
        $this->assertStringContainsString(
            '/educonnect-test/educonnect/testing/resources/v1/uploads/'.str_repeat('a', 32),
            $grant->url,
        );
        $this->assertStringNotContainsString('test-secret-key', $grant->url);
    }
}
