<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Resources;

use App\Domains\Resources\Support\LocalResourceDisk;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a stored resource file from the local disk — the local-disk stand-in
 * for an S3 presigned GET. The URL is a temporary signed route carrying the
 * object key, mime type, and download filename; the endpoint aborts unless the
 * resource disk is a local driver, keeping it inert in production.
 */
final class ServeLocalResourceDownloadController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $disk = LocalResourceDisk::require();

        $key = (string) $request->query('key', '');

        if (preg_match('#^resources/v1/objects/[0-9a-f]{32}$#', $key) !== 1) {
            abort(404);
        }

        if (! $disk->exists($key)) {
            abort(404);
        }

        $name = (string) $request->query('name', 'resource-file');
        $mime = (string) $request->query('mime', 'application/octet-stream');

        return $disk->download($key, $name, ['Content-Type' => $mime]);
    }
}
