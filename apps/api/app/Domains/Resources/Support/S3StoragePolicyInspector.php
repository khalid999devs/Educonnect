<?php

declare(strict_types=1);

namespace App\Domains\Resources\Support;

use App\Domains\Resources\Data\StoragePolicySnapshot;
use App\Domains\Resources\Exceptions\StoragePolicyInspectionFailure;
use Aws\ResultInterface;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

class S3StoragePolicyInspector
{
    public function inspect(): StoragePolicySnapshot
    {
        $diskName = config('resources.disk', 's3');

        if (! is_string($diskName) || trim($diskName) === '') {
            throw new StoragePolicyInspectionFailure('The resource storage disk configuration is invalid.');
        }

        $disk = Storage::disk($diskName);

        if (! $disk instanceof AwsS3V3Adapter) {
            throw new StoragePolicyInspectionFailure('The resource storage disk is not an S3-compatible adapter.');
        }

        $configuration = $disk->getConfig();
        $bucket = $configuration['bucket'] ?? null;
        $endpoint = $configuration['endpoint'] ?? null;
        $maxDays = config('resources.staging_lifecycle_max_days');
        $frontendOrigin = $this->frontendOrigin(config('app.frontend_url'));

        if (! is_string($bucket)
            || trim($bucket) === ''
            || ! is_int($maxDays)
            || $maxDays < 1
            || $maxDays > 365
            || $frontendOrigin === null) {
            throw new StoragePolicyInspectionFailure('The storage policy verification configuration is invalid.');
        }

        $provider = $this->provider($endpoint);
        $client = $disk->getClient();
        $lifecycle = $this->read(
            fn (): ResultInterface => $client->getBucketLifecycleConfiguration(['Bucket' => $bucket]),
            'The bucket lifecycle configuration could not be read.',
        );
        $cors = $this->read(
            fn (): ResultInterface => $client->getBucketCors(['Bucket' => $bucket]),
            'The bucket CORS configuration could not be read.',
        );
        $versioning = null;
        $publicAccessBlock = null;
        $policyStatus = null;

        if ($provider === StoragePolicySnapshot::PROVIDER_AWS) {
            $versioning = $this->read(
                fn (): ResultInterface => $client->getBucketVersioning(['Bucket' => $bucket]),
                'The bucket versioning configuration could not be read.',
            );
            $publicAccessBlock = $this->read(
                fn (): ResultInterface => $client->getPublicAccessBlock(['Bucket' => $bucket]),
                'The bucket public-access block could not be read.',
            );
            $policyStatus = $this->read(
                fn (): ResultInterface => $client->getBucketPolicyStatus(['Bucket' => $bucket]),
                'The bucket policy status could not be read.',
            );
        }

        return new StoragePolicySnapshot(
            provider: $provider,
            maxLifecycleDays: $maxDays,
            uploadPrefix: $disk->path('resources/v1/uploads/'),
            objectPrefix: $disk->path('resources/v1/objects/'),
            frontendOrigin: $frontendOrigin,
            lifecycle: $lifecycle,
            cors: $cors,
            versioning: $versioning,
            publicAccessBlock: $publicAccessBlock,
            policyStatus: $policyStatus,
        );
    }

    /** @param callable(): ResultInterface $operation
     * @return array<string, mixed>
     */
    private function read(callable $operation, string $safeFailure): array
    {
        try {
            return $operation()->toArray();
        } catch (Throwable) {
            throw new StoragePolicyInspectionFailure($safeFailure);
        }
    }

    private function provider(mixed $endpoint): string
    {
        if ($endpoint === null || $endpoint === '') {
            return StoragePolicySnapshot::PROVIDER_AWS;
        }

        if (! is_string($endpoint)) {
            return StoragePolicySnapshot::PROVIDER_CUSTOM;
        }

        $host = parse_url($endpoint, PHP_URL_HOST);

        if (is_string($host)) {
            $host = strtolower($host);

            if ($host === 'r2.cloudflarestorage.com' || str_ends_with($host, '.r2.cloudflarestorage.com')) {
                return StoragePolicySnapshot::PROVIDER_R2;
            }
        }

        return StoragePolicySnapshot::PROVIDER_CUSTOM;
    }

    private function frontendOrigin(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        $origin = strtolower((string) $parts['scheme']).'://'.strtolower((string) $parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
