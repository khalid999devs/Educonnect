<?php

declare(strict_types=1);

namespace App\Domains\Resources\Support;

use App\Domains\Resources\Contracts\ResourceUploadSigner;
use App\Domains\Resources\Data\UploadPutGrant;
use App\Domains\Resources\Exceptions\ResourceStorageFailure;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

final class ResourceStorage
{
    public function __construct(private readonly ResourceUploadSigner $uploadSigner) {}

    public function temporaryUploadUrl(
        string $key,
        CarbonImmutable $expiresAt,
        string $mimeType,
        int $size,
    ): UploadPutGrant {
        try {
            return $this->uploadSigner->sign($key, $expiresAt, $mimeType, $size);
        } catch (Throwable $exception) {
            throw ResourceStorageFailure::fromThrowable($exception, 'upload.sign');
        }
    }

    public function temporaryDownloadUrl(
        string $key,
        CarbonImmutable $expiresAt,
        string $mimeType,
    ): string {
        try {
            if (LocalResourceDisk::isActive()) {
                return URL::temporarySignedRoute('resources.local-download', $expiresAt, [
                    'key' => $key,
                    'mime' => $mimeType,
                    'name' => 'resource-file.'.$this->extensionForMimeType($mimeType),
                ]);
            }

            return $this->disk()->temporaryUrl($key, $expiresAt, [
                'ResponseContentDisposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_ATTACHMENT,
                    'resource-file.'.$this->extensionForMimeType($mimeType),
                ),
                'ResponseContentType' => $mimeType,
            ]);
        } catch (Throwable $exception) {
            throw ResourceStorageFailure::fromThrowable($exception, 'download.sign');
        }
    }

    public function size(string $key): int
    {
        try {
            return $this->disk()->size($key);
        } catch (Throwable $exception) {
            throw ResourceStorageFailure::fromThrowable($exception, 'upload.size');
        }
    }

    public function exists(string $key): bool
    {
        try {
            return $this->disk()->exists($key);
        } catch (Throwable $exception) {
            throw ResourceStorageFailure::fromThrowable($exception, 'object.exists');
        }
    }

    /** @return resource */
    public function readStream(string $key)
    {
        try {
            $stream = $this->disk()->readStream($key);

            if (! is_resource($stream)) {
                throw new \RuntimeException('The storage adapter did not return a readable stream.');
            }

            return $stream;
        } catch (Throwable $exception) {
            throw ResourceStorageFailure::fromThrowable($exception, 'upload.read');
        }
    }

    public function copy(string $source, string $destination): void
    {
        try {
            if (! $this->disk()->copy($source, $destination)) {
                throw new \RuntimeException('The storage adapter did not copy the object.');
            }
        } catch (Throwable $exception) {
            throw ResourceStorageFailure::fromThrowable($exception, 'upload.copy');
        }
    }

    /** @param list<?string> $keys */
    public function delete(array $keys): void
    {
        try {
            $keys = array_values(array_unique(array_filter($keys, is_string(...))));

            if ($keys !== [] && ! $this->disk()->delete($keys)) {
                throw new \RuntimeException('The storage adapter did not delete the objects.');
            }
        } catch (Throwable $exception) {
            throw ResourceStorageFailure::fromThrowable($exception, 'object.delete');
        }
    }

    public function uploadTtlSeconds(): int
    {
        return $this->boundedConfig('resources.upload_ttl_seconds', 600, 3600);
    }

    public function downloadTtlSeconds(): int
    {
        return $this->boundedConfig('resources.download_ttl_seconds', 300, 900);
    }

    public function cleanupGraceSeconds(): int
    {
        return $this->boundedConfig('resources.cleanup_grace_seconds', 60, 3600);
    }

    public function lateUploadReapSeconds(): int
    {
        return $this->boundedConfig('resources.late_upload_reap_seconds', 86_400, 604_800);
    }

    private function disk(): FilesystemAdapter
    {
        $name = config('resources.disk', 's3');

        return Storage::disk(is_string($name) && $name !== '' ? $name : 's3');
    }

    private function boundedConfig(string $key, int $default, int $maximum): int
    {
        $value = config($key, $default);

        return is_int($value) && $value >= 1 && $value <= $maximum ? $value : $default;
    }

    private function extensionForMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'text/plain' => 'txt',
            'text/markdown' => 'md',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            default => 'bin',
        };
    }
}
