<?php

declare(strict_types=1);

namespace App\Domains\Resources\Support;

use App\Domains\Resources\Data\FileInspectionResult;
use App\Domains\Resources\Exceptions\InvalidResourceFile;

final class ResourceFileInspector
{
    public const DEFAULT_MAX_BYTES = 26_214_400;

    /** @var array<string, list<string>> */
    private const MIME_EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'text/plain' => ['txt'],
        'text/markdown' => ['md', 'markdown'],
    ];

    public function assertUploadMetadata(
        string $originalName,
        string $mimeType,
        int $size,
        string $sha256,
    ): void {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = self::MIME_EXTENSIONS[$mimeType] ?? null;

        if ($originalName === ''
            || strlen($originalName) > 255
            || trim($originalName) !== $originalName
            || in_array($originalName, ['.', '..'], true)
            || str_contains($originalName, '/')
            || str_contains($originalName, '\\')
            || preg_match('/[\x00-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $originalName) === 1
            || ! is_array($allowedExtensions)
            || ! in_array($extension, $allowedExtensions, true)
            || $size < 1
            || $size > $this->maxBytes()
            || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw new InvalidResourceFile;
        }
    }

    /** @param resource $stream */
    public function inspect($stream, string $declaredMimeType, int $expectedSize, string $expectedSha256): FileInspectionResult
    {
        if (! is_resource($stream)) {
            throw new InvalidResourceFile;
        }

        $hash = hash_init('sha256');
        $prefix = '';
        $pendingUtf8 = '';
        $size = 0;
        $isText = in_array($declaredMimeType, ['text/plain', 'text/markdown'], true);

        while (! feof($stream)) {
            $chunk = fread($stream, 8192);

            if (! is_string($chunk)) {
                throw new InvalidResourceFile;
            }

            if ($chunk === '') {
                continue;
            }

            $size += strlen($chunk);

            if ($size > $expectedSize || $size > $this->maxBytes()) {
                throw new InvalidResourceFile;
            }

            hash_update($hash, $chunk);

            if (strlen($prefix) < 16) {
                $prefix .= substr($chunk, 0, 16 - strlen($prefix));
            }

            if ($isText) {
                if (str_contains($chunk, "\0")) {
                    throw new InvalidResourceFile;
                }

                $pendingUtf8 = $this->validateUtf8Chunk($pendingUtf8.$chunk);
            }
        }

        $sha256 = hash_final($hash);

        if ($size !== $expectedSize
            || ! hash_equals($expectedSha256, $sha256)
            || ($isText && $pendingUtf8 !== '')
            || ! $this->matchesMagic($declaredMimeType, $prefix)) {
            throw new InvalidResourceFile;
        }

        return new FileInspectionResult($declaredMimeType, $size, $sha256);
    }

    public function maxBytes(): int
    {
        $configured = config('resources.max_upload_bytes', self::DEFAULT_MAX_BYTES);

        return is_int($configured) && $configured >= 1 && $configured <= self::DEFAULT_MAX_BYTES
            ? $configured
            : self::DEFAULT_MAX_BYTES;
    }

    private function matchesMagic(string $mimeType, string $prefix): bool
    {
        return match ($mimeType) {
            'application/pdf' => str_starts_with($prefix, '%PDF-'),
            'image/jpeg' => str_starts_with($prefix, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($prefix, "\x89PNG\r\n\x1A\n"),
            'image/webp' => strlen($prefix) >= 12
                && substr($prefix, 0, 4) === 'RIFF'
                && substr($prefix, 8, 4) === 'WEBP',
            'text/plain', 'text/markdown' => true,
            default => false,
        };
    }

    private function validateUtf8Chunk(string $value): string
    {
        $length = strlen($value);

        for ($tailLength = 0; $tailLength <= min(3, $length); $tailLength++) {
            $prefixLength = $length - $tailLength;
            $prefix = substr($value, 0, $prefixLength);
            $tail = substr($value, $prefixLength);

            if (mb_check_encoding($prefix, 'UTF-8')
                && ($tailLength === 0 || $this->isIncompleteUtf8Sequence($tail))) {
                return $tail;
            }
        }

        throw new InvalidResourceFile;
    }

    private function isIncompleteUtf8Sequence(string $value): bool
    {
        $bytes = array_values(unpack('C*', $value) ?: []);
        $length = count($bytes);

        if ($length < 1 || $length > 3) {
            return false;
        }

        $expectedLength = match (true) {
            $bytes[0] >= 0xC2 && $bytes[0] <= 0xDF => 2,
            $bytes[0] >= 0xE0 && $bytes[0] <= 0xEF => 3,
            $bytes[0] >= 0xF0 && $bytes[0] <= 0xF4 => 4,
            default => 0,
        };

        if ($expectedLength <= $length) {
            return false;
        }

        for ($index = 1; $index < $length; $index++) {
            if ($bytes[$index] < 0x80 || $bytes[$index] > 0xBF) {
                return false;
            }
        }

        return true;
    }
}
