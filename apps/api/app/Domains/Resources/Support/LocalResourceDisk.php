<?php

declare(strict_types=1);

namespace App\Domains\Resources\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Guards the development-only local resource transport. The signed upload and
 * download routes, and the local branch of the download-URL builder, operate
 * only when the configured resource disk is a local driver. In production the
 * disk is S3, so these paths are inert and the strict S3 signer is used.
 */
final class LocalResourceDisk
{
    public static function isActive(): bool
    {
        $disk = config('resources.disk', 's3');

        if (! is_string($disk) || $disk === '') {
            return false;
        }

        return config("filesystems.disks.{$disk}.driver") === 'local';
    }

    public static function require(): FilesystemAdapter
    {
        if (! self::isActive()) {
            abort(404);
        }

        $disk = config('resources.disk');

        return Storage::disk(is_string($disk) && $disk !== '' ? $disk : 'local');
    }
}
