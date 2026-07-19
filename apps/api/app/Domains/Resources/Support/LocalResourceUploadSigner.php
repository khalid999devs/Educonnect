<?php

declare(strict_types=1);

namespace App\Domains\Resources\Support;

use App\Domains\Resources\Contracts\ResourceUploadSigner;
use App\Domains\Resources\Data\UploadPutGrant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

/**
 * Local-disk stand-in for the S3 presigned PUT. Instead of an object-store URL
 * it returns a short-lived signed route on this application that accepts the
 * raw body and writes it to the local resource disk. Used only when the
 * resource storage disk is a local driver (development); production keeps the
 * strict S3 signer. The key, mime, and size are covered by the signature, so
 * the browser cannot alter them.
 */
final class LocalResourceUploadSigner implements ResourceUploadSigner
{
    public function sign(
        string $key,
        CarbonImmutable $expiresAt,
        string $mimeType,
        int $size,
    ): UploadPutGrant {
        $url = URL::temporarySignedRoute('resources.local-upload', $expiresAt, [
            'key' => $key,
            'mime' => $mimeType,
            'size' => $size,
        ]);

        return new UploadPutGrant($url, ['Content-Type' => $mimeType]);
    }
}
