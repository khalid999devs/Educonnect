<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Resources;

use App\Domains\Resources\Support\LocalResourceDisk;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Accepts the raw body of a local resource upload — the local-disk stand-in for
 * an S3 presigned PUT — and writes it to the configured resource disk under the
 * signed staging key. The URL is a temporary signed route, so key/mime/size
 * cannot be tampered with, and the endpoint aborts unless the resource disk is
 * a local driver, keeping it inert in production. Byte integrity (size and
 * sha256) is re-verified by the confirm step, not here.
 */
final class StoreLocalResourceUploadController
{
    public function __invoke(Request $request): Response
    {
        $disk = LocalResourceDisk::require();

        $key = (string) $request->query('key', '');

        if (preg_match('#^resources/v1/uploads/[0-9a-f]{32}$#', $key) !== 1) {
            abort(404);
        }

        $stream = $request->getContent(true);

        if (! is_resource($stream) || $disk->writeStream($key, $stream) === false) {
            abort(500, 'The upload could not be stored.');
        }

        return response()->noContent();
    }
}
