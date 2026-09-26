<?php

declare(strict_types=1);

namespace App\Domains\Resources\Support;

use App\Domains\Resources\Contracts\ResourceUploadSigner;
use App\Domains\Resources\Data\UploadPutGrant;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class S3StrictPutUploadSigner implements ResourceUploadSigner
{
    public function sign(
        string $key,
        CarbonImmutable $expiresAt,
        string $mimeType,
        int $size,
    ): UploadPutGrant {
        $diskName = config('resources.disk', 's3');

        if (! is_string($diskName) || $diskName === '') {
            throw new RuntimeException('The resource storage disk is invalid.');
        }

        $disk = Storage::disk($diskName);

        if (! $disk instanceof AwsS3V3Adapter) {
            throw new RuntimeException('The resource storage disk does not support strict S3 upload grants.');
        }

        $configuration = $disk->getConfig();
        $bucket = $configuration['bucket'] ?? null;

        if (! is_string($bucket) || trim($bucket) === '') {
            throw new RuntimeException('The resource storage bucket is invalid.');
        }

        $client = $disk->getClient();
        $command = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $disk->path($key),
            'ContentType' => $mimeType,
            'ContentLength' => $size,
        ]);
        $command->getHandlerList()->remove('signer');
        $command->getHandlerList()->remove('s3.checksum');
        $request = \Aws\serialize($command);
        $signed = (new StrictS3SignatureV4('s3', $client->getRegion()))->presign(
            $request,
            $client->getCredentials()->wait(),
            $expiresAt,
        );
        parse_str($signed->getUri()->getQuery(), $query);
        $signedHeaders = $query['X-Amz-SignedHeaders'] ?? null;
        $normalizedSignedHeaders = is_string($signedHeaders)
            ? explode(';', strtolower($signedHeaders))
            : null;

        if ($normalizedSignedHeaders !== ['content-length', 'content-type', 'host']
            || $signed->getHeaderLine('Content-Length') !== (string) $size
            || $signed->getHeaderLine('Content-Type') !== $mimeType) {
            throw new RuntimeException('The object store upload grant is not strictly bounded.');
        }

        return new UploadPutGrant((string) $signed->getUri(), ['Content-Type' => $mimeType]);
    }
}
